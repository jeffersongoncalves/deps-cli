# Changelog

All notable changes to this project will be documented in this file.

## [1.1.4] - 2026-08-26

### Features

- Rewrite APP_URL host when copying .env from main worktree

## [1.1.3] - 2026-08-24

### CI/CD

- **release:** Generate CHANGELOG.md and release notes with git-cliff

### Features

- Copy .env from main worktree before install when run from a linked git worktree

## [1.1.2] - 2026-08-21

### Bug Fixes

- Only queue composer post-update-cmd when the script is defined

## [1.1.1] - 2026-08-18

### Features

- Support yarn and bun, ask before guessing on ambiguous package.json

## [1.1.0] - 2026-08-14

### Features

- Add self-update and global/per-repo config

## [1.0.0] - 2026-08-14

### CI/CD

- Add run-tests, code-style, and release workflows

### Documentation

- Add portfolio banner and expand README

### Other

- Initial commit

CLI tool that detects composer.json/package.json/pnpm-lock.yaml in a
directory and runs the matching install/build commands. Built with
Laravel Zero, modeled on the other CLIs in this monorepo.


