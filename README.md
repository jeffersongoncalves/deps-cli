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
- `composer`/`npm`/`pnpm` on `PATH`, as needed by the detected project

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
```

## Detection rules

Each rule is independent — a directory can trigger several of them at once,
and steps run in this order:

1. `composer.json` present → `composer install`, then `composer run post-update-cmd`
2. `package.json` present **and** `package-lock.json` present → `npm install`
3. `package.json` declares a `scripts.build` entry → `npm run build`
4. `pnpm-lock.yaml` present → `pnpm install`, then `pnpm run build`

If none of the marker files are found, the command reports "Nothing to do"
and exits successfully — safe to run against any directory.

Execution stops at the first step that fails (non-zero exit code), and its
full stdout/stderr is streamed live to the terminal as it runs.

## How it works

`App\Services\DepsPlanner::plan()` is a pure function: given a directory, it
only reads the marker files above and returns the ordered list of steps —
no process execution, no side effects. `App\Commands\InstallCommand` takes
that plan and, unless `--dry-run` is passed, runs each step with Symfony
Process (no timeout, live-streamed output) inside the target directory.

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
