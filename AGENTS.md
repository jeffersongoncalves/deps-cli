# AGENTS.md

Guidance for an LLM/agent using the `deps` CLI. Every command below is
non-interactive-capable when flagged correctly — see the package manager
prompt note below, the one place this CLI can stop and wait for input.

Binary: `deps` (or `php deps` from a source checkout).

## What it does

Detects `composer.json`, `package.json`, and JS lockfiles in a directory and
runs the matching install/build commands, in order. Supports npm, pnpm,
yarn, and bun — detected independently by their lockfile, so more than one
can fire in the same run (e.g. a repo mid-migration between managers).

| Manager | Lockfile |
|---------|----------|
| npm | `package-lock.json` |
| pnpm | `pnpm-lock.yaml` |
| yarn | `yarn.lock` |
| bun | `bun.lock` or `bun.lockb` |

## Basic usage

```bash
# Detect and install in the current directory (the default command)
deps

# Target a specific directory
deps install /path/to/project

# Show the detected steps without running them — use this first when
# scripting against an unfamiliar repo
deps install --dry-run
```

## The one interactive prompt — and how to avoid it

If `package.json` exists but none of the four lockfiles above are found,
`deps install` asks which package manager to use. An agent driving this
non-interactively **must** pass `--package-manager` to skip the prompt:

```bash
deps install /path/to/project --package-manager=pnpm
```

Valid values: `npm`, `pnpm`, `yarn`, `bun`. An invalid value fails fast
(exit `1`) instead of falling through to the prompt. If you don't know
which manager the repo wants, run `deps install --dry-run` first — if it
prints nothing about npm/pnpm/yarn/bun, no lockfile was found and you'll
need `--package-manager` (or check for a `packageManager` field in
`package.json`, or ask the user).

## Config: skip steps / append extra commands

Two levels: global (`~/.deps-cli/config.json`) and per-repo
(`~/.config/deps-cli/<slug>.json`). Per-repo `skip`/`run` fully replaces the
global value for that key — no merging.

```bash
# Skip a step
deps config:skip npm.build [path] [--global]

# Add a command that always runs after the detected steps
deps config:run 'php artisan key:generate' [path] [--global]

# Undo either
deps config:unskip composer.post-update-cmd [path] [--global]
deps config:unrun 'php artisan key:generate' [path] [--global]

# Inspect global / repo / effective (resolved) config as text
deps config:show [path]

# Ignore all config for a single run
deps install --no-config
```

Valid skip step values: `composer.install`, `composer.post-update-cmd`,
`npm.install`, `npm.build`, `pnpm.install`, `pnpm.build`, `yarn.install`,
`yarn.build`, `bun.install`, `bun.build`. An unknown value fails with exit
`1` and lists the valid set.

## Exit codes

`0` success (including "nothing to do" — safe to run against any
directory). `1` on: target path not a directory, an install/build step
exiting non-zero (stops at the first failure, no further steps run),
`config:skip` with an unknown step, `config:run`/`config:unrun` with an
empty command, or an invalid `--package-manager` value.

## Example: end-to-end from a fresh agent

```bash
deps install /path/to/repo --dry-run                       # see what would run, no prompts if a lockfile exists
deps install /path/to/repo --package-manager=pnpm           # force the manager if no lockfile is present yet
deps config:skip npm.build /path/to/repo                    # opt this repo out of a step permanently
deps install /path/to/repo --no-config                      # run once ignoring that skip
```
