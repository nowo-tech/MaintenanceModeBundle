# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [\[Unreleased\]](#unreleased)
- [\[1.3.0\] - 2026-08-03](#130---2026-08-03)
  - [Added](#added)
  - [Fixed](#fixed)
  - [Changed](#changed)
  - [Compatibility](#compatibility)
- [\[1.2.2\] - 2026-07-30](#122---2026-07-30)
  - [Fixed](#fixed-1)
  - [Compatibility](#compatibility-1)
- [\[1.2.1\] - 2026-07-30](#121---2026-07-30)
  - [Fixed](#fixed-2)
  - [Changed](#changed-1)
  - [Compatibility](#compatibility-2)
- [\[1.2.0\] - 2026-07-29](#120---2026-07-29)
  - [Added](#added-1)
  - [Fixed](#fixed-3)
  - [Changed](#changed-2)
  - [Compatibility](#compatibility-3)
- [\[1.1.2\] - 2026-07-27](#112---2026-07-27)
  - [Fixed](#fixed-4)
  - [Changed](#changed-3)
  - [Compatibility](#compatibility-4)
- [\[1.1.1\] - 2026-07-26](#111---2026-07-26)
  - [Fixed](#fixed-5)
  - [Changed](#changed-4)
  - [Compatibility](#compatibility-5)
- [\[1.1.0\] - 2026-07-24](#110---2026-07-24)
  - [Added](#added-2)
  - [Changed](#changed-5)
  - [Compatibility](#compatibility-6)
- [\[1.0.0\] - 2026-07-24](#100---2026-07-24)
  - [Added](#added-3)
  - [Compatibility](#compatibility-7)

## [Unreleased]

## [1.3.0] - 2026-08-03

### Added

- REQ-UI-002 panel access control: `security.access_roles` (default `[ROLE_ADMIN]`), optional `security.access_checker`, and `MaintenanceModeAccessCheckerInterface` (`ConfigurableMaintenanceModeAccessChecker` / `AllowAllMaintenanceModeAccessChecker`).
- `security.allow_unauthenticated` (default `false`): when `false` and the panel is enabled, `symfony/security-bundle` is required.

### Fixed

- Restore Symfony **8** on core `symfony/*` constraints again (`^7.4 || ^8.0`) after post-release automation had narrowed several packages to `^7.4` only on the published tree.

### Changed

- Ops password gate remains an **additional** layer on top of role / access-checker checks.
- Demo sets `allow_unauthenticated: true` (password gate only; never copy to production).

### Compatibility

- PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0` (CI minors **7.4**, **8.0**, **8.1**).
- Panel with default security settings requires **SecurityBundle** (or set `allow_unauthenticated: true` for trusted local demos).

## [1.2.2] - 2026-07-30

### Fixed

- **Security (REQ-SEC-005):** panel CSRF validation is **fail-closed** — when `CsrfTokenManagerInterface` is missing, mutating panel actions (enable/disable/schedule/login/logout) return HTTP 403 instead of skipping CSRF checks.

### Compatibility

- Unchanged: PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0`. Requires `symfony/security-csrf` for panel mutations.

## [1.2.1] - 2026-07-30

### Fixed

- Restore Symfony **8** on core `symfony/*` constraints (`config`, `dependency-injection`, `http-foundation`, `http-kernel`, `security-core`) after a post-`v1.2.0` automation commit had narrowed them back to `^7.4` only.

### Changed

- README section order: Requirements / Demo / Development before Documentation.
- Panel default layout: Twig comment clarifying root HTML (no `parent()` in `stylesheets` / `javascripts`).

### Compatibility

- Unchanged: PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0` (CI minors **7.4**, **8.0**, **8.1**).

## [1.2.0] - 2026-07-29

### Added

- REQ-UI-001 `web_ui` (`layout_template`, `css_framework`, `icon_set`) + Twig globals (`nowo_maintenance_mode_*`) and semantic `nowo-ui-*` panel markup.
- `make demo-smoke` / `make -C demo demo-smoke` (REQ-TEST-011); PHPUnit `SYMFONY_DEPRECATIONS_HELPER=max[direct]=0` (REQ-SF-005).
- TOC on long docs (REQ-DOCS-005); GitHub About / topics notes (REQ-DOCS-018).
- REQ-SEC-004 Pass (conditional) recorded in `docs/SECURITY.md`.

### Fixed

- Composer constraints for core `symfony/*` packages (`config`, `dependency-injection`, `http-foundation`, `http-kernel`, `security-core`) correctly allow Symfony **8** (`^7.4 || ^8.0`) — completes the 1.1.2 intent when those pins were still `^7.4` only on the published tree.

### Changed

- Makefiles prefer `docker compose` (V2) with V1 fallback; optional monorepo `update-deps` includes use `-include` for standalone CI checkouts (REQ-MAKE-009 / REQ-MAKE-010).
- `web_ui.layout_template` is canonical and stays in sync with legacy `templates.panel_layout`.

### Compatibility

- Unchanged: PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0` (CI minors **7.4**, **8.0**, **8.1**).

## [1.1.2] - 2026-07-27

### Fixed

- Composer constraints for `symfony/config`, `dependency-injection`, `http-foundation`, `http-kernel`, and `security-core` allow Symfony **8** (`^7.4 || ^8.0`). Tag `v1.1.1` used `^7.4` alone and was not installable on Symfony 8 apps.

### Changed

- Documented supported floor as Symfony **7.4** / **8.0** / **8.1** (PHP `>=8.2`, `<8.6`; Symfony 8.x needs PHP **8.4+**). Dropped misleading claims of Symfony 7.0–7.3 support where core packages require `^7.4`.
- CI Symfony matrix keeps mandatory minors **7.4**, **8.0**, and **8.1** (optional `7.0` cell removed).

### Compatibility

- PHP `>=8.2`, `<8.6` (Symfony **8.x** requires PHP **8.4+**)
- Symfony `^7.4 || ^8.0` (CI / mandatory minors: **7.4**, **8.0**, **8.1**)

## [1.1.1] - 2026-07-26

### Fixed

- Preview controller accepts `default_message: null` (and treats empty string as null) so apps can rely on Twig translation (`maintenance.page.message`).

### Changed

- Recipe and package sample config document `preview.enabled` / `preview.path`.
- Nowo bundle standards: canonical `docs/GITHUB_CI.md` (REQ-GIT-001), Spec Kit skills + `.specify/`, Copilot instructions, expanded FrankenPHP / security docs, demo `.gitignore` archive and cache patterns.

### Compatibility

- PHP `>=8.2`, `<8.6`; Symfony floor **7.4** with optional peripheral `^7.0` constraints (CI minors **7.4**, **8.0**, **8.1**). Note: `v1.1.1` core `symfony/*` packages were `^7.4` only — Symfony 8 installs need **1.1.2+**.

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

- Unchanged: PHP `>=8.2`, `<8.6`; Symfony floor **7.4** (CI minors **7.4**, **8.0**, **8.1**).

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
- Symfony floor **7.4** (CI / mandatory minors: **7.4**, **8.0**, **8.1**)

[Unreleased]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.3.0...HEAD
[1.3.0]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.2.2...v1.3.0
[1.2.2]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.1.2...v1.2.0
[1.1.2]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.0.0
