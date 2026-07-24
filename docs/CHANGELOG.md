# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-07-24

First stable release.

### Added

- Maintenance mode via high-priority `kernel.request` subscriber (HTTP **503** + `Retry-After`).
- Configurable exclusions: exact paths, path prefixes, route names, globs (`fnmatch`), and `#regex#` / `~regex~`.
- Twig admin panel (`/_maintenance`) with enable / disable / schedule / history and CSRF-protected POSTs.
- Optional password gate (`password_hash`, bcrypt / argon2id); pluggable `MaintenanceAccessGateInterface`.
- Pluggable filesystem state (JSON/YAML) and append-only history (JSONL); override via DI (`storage.state_storage` / `history_storage`).
- Overrideable Twig templates + translations for **en / es / it / fr / pt / de / nl** (`NowoMaintenanceModeBundle`).
- Symfony Flex recipe under `.symfony/recipe/`.
- FrankenPHP demo: Symfony **8.1** on port **8055** (`FRANKENPHP_MODE=worker` by default).
- Spec Kit baseline under `specs/001-baseline/`.
- QA toolchain: PHPUnit (100% lines), PHP-CS-Fixer, PHPStan (+ FrankenPHP rulesets), Rector.

### Compatibility

- PHP `>=8.2`, `<8.6` (Symfony **8.x** requires PHP **8.4+**)
- Symfony `^7.0 || ^8.0` (CI / mandatory minors: **7.4**, **8.0**, **8.1**; also tested against **7.0**)
