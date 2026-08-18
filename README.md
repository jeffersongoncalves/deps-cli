<div class="filament-hidden">

![Deps CLI](https://raw.githubusercontent.com/jeffersongoncalves/deps-cli/main/art/jeffersongoncalves-deps-cli.png)

</div>

# deps-cli

CLI tool that detects `composer.json`, `package.json`, and lockfiles in a
directory and runs the matching install/build commands, in order, so you
don't have to remember which package manager a given repo uses.

Built with [Laravel Zero](https://laravel-zero.com) and modeled on the
other CLIs in this monorepo.

## Requirements

- PHP `^8.2`
- `composer`/`npm`/`pnpm`/`yarn`/`bun` on `PATH`, as needed by the detected project

## Install

### Global (recommended)

```bash
composer global require jeffersongoncalves/deps-cli
```

The binary `deps` will be on your `PATH` as long as Composer's global
`vendor/bin` is in it.

### From source

```bash
git clone https://github.com/jeffersongoncalves/deps-cli.git
cd deps-cli
composer install
```

## Usage

```bash
# Detect and install in the current directory (the default command)
deps

# Target a specific directory
deps install /path/to/project

# Show the detected steps without running them
deps install --dry-run

# Pick the manager explicitly, skipping the interactive prompt
deps install --package-manager=pnpm
```

## Detection rules

Each rule is independent — a directory can trigger several of them at once,
and steps run in this order:

1. `composer.json` present → `composer install`, then `composer run post-update-cmd`
2. `package.json` present **and** a lockfile matches one of the JS package
   managers below → `<manager> install`, then, if `package.json` declares a
   `scripts.build` entry, `<manager> run build`

| Manager | Lockfile |
|---------|----------|
| npm | `package-lock.json` |
| pnpm | `pnpm-lock.yaml` |
| yarn | `yarn.lock` |
| bun | `bun.lock` or `bun.lockb` |

More than one lockfile can be present at once (e.g. a repo mid-migration
from npm to pnpm) — each detected manager gets its own install/build steps.

If `package.json` exists but **no** lockfile is found, `deps` prompts you to
pick a manager (defaulting to whichever of npm/pnpm/yarn/bun is actually on
`PATH`) instead of silently guessing or skipping the step. Pass
`--package-manager=npm|pnpm|yarn|bun` to answer that upfront and skip the
prompt (required for non-interactive/CI use).

If none of the marker files are found, the command reports "Nothing to do"
and exits successfully — safe to run against any directory.

Execution stops at the first step that fails (non-zero exit code), and its
full stdout/stderr is streamed live to the terminal as it runs.

## Configuration

Skip specific steps or append extra commands, at two levels:

- **Global** — `~/.deps-cli/config.json`, the default for every directory.
- **Per-repo** — `~/.config/deps-cli/<slug>.json` (XDG-aware), keyed by the
  origin remote (`owner-repo`) or, without a remote, the directory name
  plus a short hash. When a repo defines its own `skip` or `run`, it
  **replaces** the global value for that key entirely (no merging).

```bash
# Never run npm's build step, everywhere
deps config:skip npm.build --global

# For this repo only, also skip composer's post-update-cmd
deps config:skip composer.post-update-cmd

# Always run an extra command after the detected steps (this repo only)
deps config:run 'php artisan key:generate'

# Undo either one
deps config:unskip composer.post-update-cmd
deps config:unrun 'php artisan key:generate'

# Inspect global / repo / effective (resolved) config
deps config:show

# Ignore config for a single run
deps install --no-config
```

Valid skip steps: `composer.install`, `composer.post-update-cmd`,
`npm.install`, `npm.build`, `pnpm.install`, `pnpm.build`,
`yarn.install`, `yarn.build`, `bun.install`, `bun.build`.

Pass `--global` to any `config:*` command to target the global file instead
of the current directory's.

## How it works

`App\Services\DepsPlanner::plan()` is a pure function: given a directory, a
skip list, extra run commands, and (only used when `package.json` has no
lockfile) a chosen package manager, it only reads the marker files above and
returns the ordered list of steps — no process execution, no side effects.
`App\Commands\InstallCommand` resolves the effective skip/run config via
`App\Services\DepsConfigService`, prompts for a package manager when needed
(defaulting to whatever `Symfony\Component\Process\ExecutableFinder` finds
on `PATH`), builds the plan, and — unless `--dry-run` is passed — runs each
step with Symfony Process (no timeout, live-streamed output) inside the
target directory.

## Development

```bash
composer install
composer test       # Pest tests + Pint lint
composer lint        # Auto-fix style
composer build        # Build the PHAR into builds/deps
```

The PHAR is emitted at `builds/deps`. The `release` GitHub Actions workflow
(manual `workflow_dispatch`) builds it fresh, stamps `version.txt`, updates
`CHANGELOG.md`, commits it to `main`, then tags and publishes the release.

## Release

1. Trigger the `Release` workflow from the Actions tab (optionally passing
   an explicit version; otherwise the patch version auto-bumps).
2. CI builds `builds/deps` against the new version, updates the changelog,
   commits to `main`, creates the tag/release, and attaches `deps.phar`.
