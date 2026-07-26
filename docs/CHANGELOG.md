# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.1.1] - 2026-07-26

### Fixed

- Preview controller accepts `default_message: null` (and treats empty string as null) so apps can rely on Twig translation (`maintenance.page.message`).

### Changed

- Recipe and package sample config document `preview.enabled` / `preview.path`.
- Nowo bundle standards: canonical `docs/GITHUB_CI.md` (REQ-GIT-001), Spec Kit skills + `.specify/`, Copilot instructions, expanded FrankenPHP / security docs, demo `.gitignore` archive and cache patterns.

### Compatibility

- Unchanged: PHP `>=8.2`, `<8.6`; Symfony `^7.0 || ^8.0` (CI minors **7.4**, **8.0**, **8.1**).

## [1.1.0] - 2026-07-24

### Added

- Dev preview route `/_maintenance_preview` (on when `kernel.debug` by default) to render the configured public maintenance page like Symfony `/_error/503`.
- Console commands `nowo:maintenance-mode:enable`, `disable`, `status` (exit `2` when effectively on; `--until` on enable), and `hash-password`.
- Soft QA bypass: `security.bypass_token` via query parameter and optional HttpOnly cookie.
- IP / CIDR exclusions (`exclusions.ips`) via `IpUtils` (set `framework.trusted_proxies` behind proxies).
- PHP attribute `#[ExcludeFromMaintenance]` and route default `_maintenance_exclude`.
- Domain events: `MaintenanceEnabledEvent`, `MaintenanceDisabledEvent`, `MaintenanceUpdatedEvent`.
- Twig helpers `nowo_maintenance_is_enabled()` and `nowo_maintenance_state()`.
- Panel: clear-schedule action, flash messages, manual vs effectively-on status, logout only when password is required.
- JSON 503 when the client prefers `application/json`; dynamic `Retry-After` from `scheduledDisableAt`; `Cache-Control: no-store`.
- Six calm framework examples (Bootstrap 5.3, Foundation 6.9, Tailwind CSS 4) plus six “idea” patterns (short, compassionate, playful, brand, countdown, updates) in **en / es / it / fr / pt / de / nl**; demo gallery at `/examples`.
- Demo exclusion smoke endpoints + `/examples/bypass` guide; docs clarify that the public 503 page must not link to panel login and that `security.password_protection` toggles the login section.

### Changed

- Default 503 page and `default_message` use friendlier, low-anxiety wording (`maintenance.page.*` keys).
- Listener registered as `kernel.event_listener` so `subscriber_priority` is honoured (default **31**, after the router; was **32** in 1.0.0).
- `MaintenanceManager::schedule()` supports `clearMissing`; added `clearSchedule()` and `isEffectivelyEnabled()`.

### Compatibility

- Unchanged: PHP `>=8.2`, `<8.6`; Symfony `^7.0 || ^8.0` (CI minors **7.4**, **8.0**, **8.1**).

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

[Unreleased]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.1.1...HEAD
[1.1.1]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.0.0
