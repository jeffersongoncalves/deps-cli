# deps-cli

CLI tool that detects `composer.json`, `package.json`, and lockfiles in a
directory and runs the matching install/build commands:

- `composer.json` present → `composer install`, then `composer run post-update-cmd`
- `package.json` present and `package-lock.json` present → `npm install`
- `package.json` declares a `build` script → `npm run build`
- `pnpm-lock.yaml` present → `pnpm install`, then `pnpm run build`

Built with [Laravel Zero](https://laravel-zero.com) and modeled on the other
CLIs in this monorepo.

## Requirements

- PHP `^8.2`
- `composer`/`npm`/`pnpm` on `PATH` as needed by the detected project

## Install

### Global (recommended)

```bash
composer global require jeffersongoncalves/deps-cli
```

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
