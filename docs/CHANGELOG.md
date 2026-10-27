# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.12.0] - 2026-10-23](#1120---2026-10-23)
- [[1.11.0] - 2026-10-21](#1110---2026-10-21)
- [[1.10.2] - 2026-10-19](#1102---2026-10-19)
- [[1.10.1] - 2026-10-18](#1101---2026-10-18)
- [[1.10.0] - 2026-10-18](#1100---2026-10-18)
- [[1.9.0] - 2026-10-13](#190---2026-10-13)
- [[1.8.0] - 2026-10-13](#180---2026-10-13)
- [[1.7.1] - 2026-10-13](#171---2026-10-13)
- [[1.7.0] - 2026-10-13](#170---2026-10-13)
- [[1.6.1] - 2026-10-09](#161---2026-10-09)
- [[1.6.0] - 2026-09-28](#160---2026-09-28)
- [[1.5.9] - 2026-09-27](#159---2026-09-27)
- [[1.5.8] - 2026-09-24](#158---2026-09-24)
- [[1.5.7] - 2026-08-24](#157---2026-08-24)
- [[1.5.6] - 2026-08-20](#156---2026-08-20)
- [[1.5.5] - 2026-08-19](#155---2026-08-19)
- [[1.5.4] - 2026-08-18](#154---2026-08-18)
- [[1.5.3] - 2026-08-13](#153---2026-08-13)
- [[1.5.2] - 2026-08-13](#152---2026-08-13)
- [[1.5.1] - 2026-08-13](#151---2026-08-13)
- [[1.5.0] - 2026-08-13](#150---2026-08-13)
- [[1.4.1] - 2026-08-13](#141---2026-08-13)
- [[1.4.0] - 2026-08-04](#140---2026-08-04)
- [[1.3.0] - 2026-08-03](#130---2026-08-03)
- [[1.2.2] - 2026-07-30](#122---2026-07-30)
- [[1.2.1] - 2026-07-30](#121---2026-07-30)
- [[1.2.0] - 2026-07-29](#120---2026-07-29)
- [[1.1.2] - 2026-07-27](#112---2026-07-27)
- [[1.1.1] - 2026-07-26](#111---2026-07-26)
- [[1.1.0] - 2026-07-24](#110---2026-07-24)
- [[1.0.0] - 2026-07-24](#100---2026-07-24)

## [Unreleased]

### Security

- CSRF fails closed also when only form CSRF is disabled: new `CsrfProtectionPass` makes the container refuse to compile while the panel (`web_ui.enabled: true`) is registered and `security.csrf.token_manager` or `form.type_extension.csrf` is missing, or `framework.form.csrf_protection` is false. The controller already denied mutations without a token manager, but with `framework.form.csrf_protection: false` the token manager exists while the panel forms (`AbstractMaintenanceFormType` declares its own CSRF options) skipped validation, so enable / disable / schedule accepted tokenless cross-site POSTs. See [UPGRADING](UPGRADING.md#unreleased) and [SECURITY](SECURITY.md#mitigations).

## [1.12.0] - 2026-10-23

### Added

- Public page options, so hosts no longer override `maintenance/page.html.twig` for them: `page.illustration` (image
  above the badge, WebP first) and `page.countdown` (a live countdown to the scheduled end, the script nonced from
  `page.csp_nonce_attribute`; without it the page still ships no JavaScript).
- Panel options: `web_ui.show_preview_link` (a link to the public page preview under the status) and
  `web_ui.form_theme` (form theme of the section forms, e.g. UiKit's `nowo_ui_layout`). New key
  `panel.status.preview` in the 7 locales.

## [1.11.0] - 2026-10-21

Injectable clock and logger, attribute preview route, CI PHPStan, release-check gates, dependency refresh. See [UPGRADING](UPGRADING.md#to-1110).

### Changed

- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19).
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).
- Time-dependent services (`MaintenanceManager`, `MaintenanceRequestSubscriber`, `MaintenancePreviewController`, `PasswordMaintenanceAccessGate`) take an injectable `Psr\Clock\ClockInterface` (the `clock` service; new `symfony/clock` / `psr/clock` requirements) instead of reading the system time directly (REQ-DI-001).
- `MaintenanceManager` logs enable / disable / schedule / update and `PasswordMaintenanceAccessGate` logs panel login success, failures and lockouts through the injected `logger`, with structured context and no operator names, IPs or secrets; documented in `docs/SECURITY.md` (REQ-OBS-001).
- The preview route `nowo_maintenance_mode_preview` is declared with `#[Route]` on `MaintenancePreviewController` (same name and configurable path); `routes.yaml` only imports attributes (REQ-SF-004).
- The request subscriber keeps its tags in the extension because its `kernel.request` priority is configurable (documented exception, REQ-SF-003).
- CI: new `phpstan` job on every push and pull request (REQ-CS-006).
- `release-check` runs sequentially from `ensure-up` and adds `check-open-prs` and `check-no-raw-html-form` (REQ-MAKE-002).
- README documents the `@codeCoverageIgnore` exclusions (filesystem error branches, defensive returns) (REQ-TEST-003).

### Fixed

- `.scripts/check-no-cursor-coauthor.sh` also runs inside git worktrees (`.git` file instead of directory).
- Documentation: wave-1 REQ compliance — canonical issue templates, README documentation list order (PSR under Additional documentation), CONTRIBUTING Cursor Agent queue settings (REQ-IDE-006), tables of contents on long docs, Spec Kit inventory refresh after the UiKit panel migration (66/66).

[1.11.0]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.11.0

## [1.10.2] - 2026-10-19

Panel and login fields render with the UiKit form theme (input slots). See [UPGRADING](UPGRADING.md#to-1102).

### Fixed

- **Panel / login forms:** the message `<textarea>`, the scheduled enable / disable `datetime-local` inputs and the login password field rendered with the host's global form theme only (form_div_layout on most UiKit hosts), so they had no UiKit `input` slot class: unstyled and not full width. The panel sections (`SettingsSection::formThemes`) and the login fields now use the UiKit form theme `@NowoUiKit/form/nowo_ui_layout.html.twig` (`MaintenancePages::FORM_THEME`): textareas get `input_full`, the other fields `input`.

[1.10.2]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.10.2

## [1.10.1] - 2026-10-18

Requires UiKit ^2.2 (section actions, flash messages, JS-free 503 through UiKit). See [UPGRADING](UPGRADING.md#to-1101).

### Changed

- Requires `nowo-tech/ui-kit-bundle` **^2.2**. See [UPGRADING](UPGRADING.md#unreleased).
- Panel: the Disable / Clear schedule actions are `SettingsSection::actions` (UiKit 2.2), printed by the settings
  scaffold on the same row as the section's submit button, instead of a bundle `toolbar` under the form. The
  `section_actions` template variable is kept (same actions) for overrides.
- Panel forms carry a unique `<form id>` (`nowo-maintenance-<form>-form`), which the scaffold's submit button targets
  (`form="…"`).
- Public 503: `AuthPage::scripts = false` and `nowo_ui_scripts_enabled = false` (UiKit 2.2 JS-free pages);
  `nowo_ui_theme_boot = false` is kept for host guest layouts that only read that flag.

### Removed

- The `flashes: null` workaround of the panel / history / login flashes block: UiKit 2.2 ignores the 1.x `flashes`
  variable inside page scaffolds, so the session flashes are always consumed.

### Demo

- Host layouts print the UiKit runtimes through the UiKit `scripts` partial (skipped on JS-free pages).

[1.10.1]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.10.1

## [1.10.0] - 2026-10-18

Panel pages and the public 503 on the UiKit 2.1 standard page contract (REQ-UI-003): settings, list and auth scaffolds, JS-free public page on the guest layout, area nav; deprecated panel/templates keys bridged. See [UPGRADING](UPGRADING.md#to-1100).

### Added

- Panel pages on the **UiKit 2.1 page contract** (REQ-UI-003): the panel renders `@NowoUiKit/page/settings` (sections status, manual control, schedule), the history `@NowoUiKit/page/list` (in-memory pagination, `web_ui.history_limit` 100 / `web_ui.history_per_page` 25, empty state) and the password login `@NowoUiKit/page/auth` on the guest layout. View models built by `Nowo\MaintenanceModeBundle\View\MaintenancePages`; pages rendered by `Nowo\MaintenanceModeBundle\Panel\PanelView`.
- Area navigation Panel | History as `PageHeader::tabs`, published to the UiKit area-nav registry as area `maintenance_mode` (`PanelView` implements `AreaNavProviderInterface`).
- Canonical keys `web_ui.path_prefix`, `web_ui.templates.<page>` (`index`, `history`, `login`), `web_ui.history_limit`, `web_ui.history_per_page`.
- The public page and the preview receive `page` (`View\AuthPage`), so a `templates.page` host template can extend `@NowoUiKit/page/auth.html.twig`.
- `docs/TEMPLATES.md`; page contract test (`tests/Integration/Contract/PageContractTest.php`) and functional kernel tests of the 503 and the panel.
- Translations `panel.intro`, `panel.manual`, `panel.manual.intro`, `panel.schedule.intro`, `panel.history.intro` (7 locales).

### Changed

- **Template contract**: the public maintenance page renders on the UiKit guest layout (`nowo_ui_kit.layouts.guest`) with the theme slots and `--nowo-ui-*` tokens: no Google Fonts / CDN, no inline `<style>`, no JavaScript (no UiKit scripts, no theme-boot script), no flashes (never starts the session). The 1.7 `maintenance_*` blocks are kept (except `maintenance_html_attributes` / `maintenance_body_attributes`, no longer rendered).
- The 503 subscriber falls back to the built-in minimal page when the configured template throws (the preview route still shows the error).
- `web_ui.layout_template` defaults to `null` (UiKit admin layout); a 1.x layout that renders `body` is wrapped in the deprecated `panel/_legacy_layout.html.twig` bridge. `web_ui.enabled` now controls the panel routes (it replaces `panel.enabled`).
- Panel flashes render through the UiKit `flashes` partial (translated with the bundle domain); the 1.x `flashes` variable is peeked, not consumed.
- The CDN example templates under `maintenance/examples/` are documented as opt-in samples (not defaults).
- Requires `nowo-tech/ui-kit-bundle` ^2.1; dev: PHPUnit ^11.

### Deprecated

- `panel.enabled`, `panel.path_prefix` → `web_ui.enabled`, `web_ui.path_prefix`.
- `templates.panel_layout` → `web_ui.layout_template`; `templates.panel_index|panel_history|panel_login` → `web_ui.templates.index|history|login`.
- `web_ui.css_framework`, `web_ui.icon_set` → `nowo_ui_kit.css_framework`, `nowo_ui_kit.icon_set` (seeded when set).
- `web_ui.classes` (and `Twig\PanelChrome`) → `nowo_ui_kit.theme.bundles.NowoMaintenanceModeBundle.classes` (bridged).
- `web_ui.partials`, `web_ui.show_page_header`, `web_ui.document_title`, `web_ui.test_ids` (no effect).
- Twig globals `nowo_maintenance_mode_layout_template`, `nowo_maintenance_mode_css_framework`, `nowo_maintenance_mode_icon_set`, `nowo_maintenance_mode_chrome`.
- 1.x body layouts as `web_ui.layout_template` (bridged with a runtime deprecation).

### Removed

- Local chrome: `panel/base.html.twig`, `panel/layout.html.twig` (standalone HTML page with inline CSS), `panel/_nav.html.twig`, `panel/_nav_tabs.html.twig`, `panel/_form_fields.html.twig`, the panel `maintenance_*` blocks and the inline `style=""` margins.

[1.10.0]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.10.0

## [1.9.0] - 2026-10-13

### Security

- `exclusions.path_prefixes` are now segment-aware, like the panel prefix in 1.8.0: `/api` exempts `/api` and `/api/…` but no longer unrelated paths such as `/apix` or `/api-docs`. Append `*` (e.g. `/api*`) to keep a raw string-prefix match; a prefix ending in `/` is matched as-is.

## [1.8.0] - 2026-10-13

### Security

- Panel password login now migrates the session id on success (`$session->migrate(true)`), preventing session fixation.
- Panel password attempts are throttled: after `security.login_max_attempts` (default **5**) failures within `security.login_lockout_seconds` (default **900**) per session and per client IP (via `cache.app` when available), the password is not checked and the login form answers **429**.
- The bypass cookie no longer stores the raw `security.bypass_token`; it holds an HMAC-SHA256 value derived from it. The `?maintenance_bypass=TOKEN` query parameter keeps working.
- Panel path prefix matching is now segment-aware: `/_maintenance` no longer exempts unrelated paths such as `/_maintenance-public` from the maintenance page (subscriber and auto-added exclusion).

### Changed

- New options `security.login_max_attempts` (`0` disables throttling) and `security.login_lockout_seconds`; new translation key `panel.login.locked`.

## [1.7.1] - 2026-10-13

### Changed

- Repository: `.twig-cs-fixer.cache` is now git-ignored.

### Dependencies

- `igor-php/igor-php` 0.9.7 → 0.10.1 (require-dev constraint + development lock; #19).

## [1.7.0] - 2026-10-13

### Added

- **Admin chrome hooks** ([spec 002](../specs/002-admin-chrome-hooks/spec.md)) so hosts theme the panel without forking pages:
  - `maintenance_*` Twig blocks on `panel/index`, `panel/history`, `panel/login` (nav, page header / title / intro / header actions, flashes, status, state details, forms, toolbar, table wrapper / head / row / row actions, empty state, pagination) and on the public `maintenance/page` (html / body attributes, `title`, head, fonts, styles, head extra, brand, heading, message, reassure, footer, scripts).
  - `web_ui.classes` CSS class map (defaults = previous hard-coded classes; empty / `null` drops the attribute).
  - `web_ui.partials.nav` / `form_fields` / `pagination`; new `panel/_nav.html.twig` (default) and opt-in `panel/_nav_tabs.html.twig` (UiKit tabs, `aria-current`).
  - `web_ui.show_page_header`, `web_ui.document_title`, `web_ui.test_ids` flags.
  - Twig global `nowo_maintenance_mode_chrome`; defaults in `Nowo\MaintenanceModeBundle\Twig\PanelChrome`.
  - Translation key `panel.nav.aria_label` (7 locales) for the formerly hard-coded nav `aria-label`.
- **Docs:** USAGE "Customising the admin chrome" (with a per-fork verdict for symfony-beacon); CONFIGURATION class-map table.
- **Tests:** golden render tests (default output byte-identical to 1.6.1 apart from the logout fix below) and override tests for every hook.

### Fixed

- Panel index: the logout button no longer renders when no password gate is active (`password_protection: false` or no `password_hash`). The template used `password_required|default(true)`, and Twig's `default` treats `false` as empty; it now uses `password_required ?? true`.

### Dependencies

- Dev lock: `nowo-tech/ui-kit-bundle` 1.12.0 (render tests pass on 1.9.2 and 1.12.0). Demo `symfony8`: regenerated `config/reference.php`.

### Changed

- Development: `composer.json` pins `config.platform.php` to 8.2.0 so the committed lock stays installable on the minimum PHP; CI overrides the platform per matrix cell. Dev lock re-resolved for PHP 8.2 (Symfony components 8.1.x → 7.4.x, `symfony/polyfill-deepclone` dropped).

## [1.6.1] - 2026-10-09

### Dependencies

- Lock refreshed: `nowo-tech/ui-kit-bundle` 1.9.1 (Dependabot 1.8.4/1.8.5 + update), Symfony 8.1.8 (`symfony` group), PHPStan 2.3.1, `phpstan/phpstan-symfony` 2.1.0, Rector 2.7.0, `nowo-tech/phpstan-frankenphp` 1.2.3.
- Demo `symfony8`: regenerated `config/reference.php`.

[1.9.0]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.9.0
[1.8.0]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.8.0
[1.7.1]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.7.1
[1.7.0]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.7.0
[1.6.1]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.6.1

## [1.6.0] - 2026-09-28

### Security

- Empty `security.access_roles` is fail-closed (deny) unless `allow_unauthenticated` or a custom `access_checker` is set.

## [1.5.9] - 2026-09-27

### Added

- **REQ-CS-008:** `igor-php/igor-php` (require-dev only), root `igor.json`, Composer/`Makefile` `igor` target, and `release-check` wiring for FrankenPHP worker-state audit.

### Changed

- **Worker safety (Igor):** justified `// @igor-ignore` annotations and/or `ResetInterface` / request-scoped fixes so `make igor` passes on package `src/`.

[1.6.0]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.6.0
[1.5.9]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.5.9

## [1.5.8] - 2026-09-24

### Added

- **Docs:** [FrankenPHP worker audit](FRANKENPHP-WORKER-AUDIT.md) — scenario A vs B (`reset_kernel false`); verdict **100% compatible under B** (stateless services, state re-read from storage every request).
- **Tests:** `WorkerModeNoKernelResetTest` — consecutive requests on the same subscriber / gate instances without `services_resetter`.

### Changed

- Spec baseline: FR-19 FrankenPHP worker safety (`reset_kernel false`).
- README / DEMO links to the worker audit.

### Compatibility

- Unchanged: PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0`. No application configuration changes.

[1.5.8]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.5.8

## [1.5.7] - 2026-08-24

### Changed

- **Docs:** PHP-FIG PSR evaluation (REQ-CS-007).
- **Style:** PHP CS Fixer alignment.

### Notes

- **No API or configuration changes** for integrators unless noted above.

[1.5.7]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.5.7

## [1.5.6] - 2026-08-20

### Security

- **Flex recipe:** default `security.access_roles: [ROLE_ADMIN]`; `when@prod` disables preview and keeps `allow_unauthenticated: false`; ship `security_nowo_maintenance_mode.yaml`. Prefer **`^1.5.6`**.

[1.5.6]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.5.6

## [1.5.5] - 2026-08-19

### Security

- **CI:** run `composer audit --locked` after dependency install (REQ-SEC / P3).

## [1.5.4] - 2026-08-18

### Changed

- **Demos:** pin `nowo-tech/hot-reload-bundle` to `^1.4` with FrankenPHP Mercure/`hot_reload` (`dev`/`test` only).

[1.5.4]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.5.4

## [1.5.3] - 2026-08-13

### Fixed

- Restore Symfony **8** on `symfony/config`, `dependency-injection`, `http-foundation`, `http-kernel`, and `security-core` after a post-`v1.5.1` Code Style auto-commit narrowed them again before `v1.5.2` landed (the CI guard from 1.5.2 prevents a repeat).

### Compatibility

- Unchanged intent: PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0`.

[1.5.3]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.5.3

## [1.5.2] - 2026-08-13

### Fixed

- CI **Code Style** job restores `composer.json` / `composer.lock` after the Symfony **7.4** matrix install so the auto-commit no longer republishes `^7.4`-only pins for `config`, `dependency-injection`, `http-foundation`, `http-kernel`, and `security-core` (root cause of the 1.2.1 / 1.3.0 / 1.4.1 / 1.5.1 constraint regressions).
- Tests for `MaintenanceModeExtension::prepend` / UiKit seeding (restores **100%** line coverage).

### Changed

- **INSTALLATION:** document runtime deps `symfony/form` and `nowo-tech/ui-kit-bundle`.
- **USAGE:** document panel Symfony Forms + `_form_fields.html.twig` unrendered-field loop for host overrides.
- Demo Symfony 8: Flex recipes for `symfony/form` CSRF + `property_info` (needed after panel Forms / UiKit).

### Compatibility

- Unchanged: PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0`.

[1.5.2]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.5.2

## [1.5.1] - 2026-08-13

### Fixed

- Restore Symfony **8** on `symfony/config`, `dependency-injection`, `http-foundation`, `http-kernel`, and `security-core` after a CS Fixer bot commit narrowed them to `^7.4` only on `main`.

### Compatibility

- Unchanged intent from 1.5.0: PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0`.

[1.5.1]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.5.1

## [1.5.0] - 2026-08-13

### Changed

- **Panel forms:** enable / disable / schedule / clear-schedule / login / logout now use Symfony `FormType` classes under `Nowo\MaintenanceModeBundle\Form\` with Twig `form_start` + unrendered-field `form_row` loop (`panel/_form_fields.html.twig`). CSRF stays on the same token ids (`nowo_maintenance_*`) and flat field names (empty block prefix). CSRF-only actions post a hidden `confirmed=1` field so unnamed forms submit correctly.
- Requires **`symfony/form`** (`^7.4 || ^8.0`).

### Compatibility

- Hosts that **override** `panel/index.html.twig` / `panel/login.html.twig` must switch to the FormView variables (`enable_form`, `disable_form`, `schedule_form`, `clear_schedule_form`, `logout_form`, `login_form`) or keep posting the same flat field names + `_token` (still accepted). See UPGRADING.
- PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0`.

[1.5.0]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.5.0

## [1.4.1] - 2026-08-13

### Fixed

- Restore Symfony **8** on core `symfony/*` constraints (`config`, `dependency-injection`, `http-foundation`, `http-kernel`, `security-core`) after `v1.4.0` published them as `^7.4` only and blocked installs on Symfony **8.0** / **8.1**.

### Compatibility

- Unchanged intent: PHP `>=8.2`, `<8.6`; Symfony `^7.4 || ^8.0` (CI minors **7.4**, **8.0**, **8.1**).

[1.4.1]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.4.1

## [1.4.0] - 2026-08-04

### Added
- **REQ-TWIG-004:** require `twig/extra-bundle` + `twig/string-extra`; `make check-twig-extra` in `release-check`; demos register `TwigExtraBundle`.
- **Twig-CS-Fixer:** `vincentlanglet/twig-cs-fixer`, `.twig-cs-fixer.php`, `composer twig:lint` / `twig:fix`.

### Changed

- **REQ-UI-001-kit:** Requires **[UiKitBundle](https://github.com/nowo-tech/UiKitBundle)** (`nowo-tech/ui-kit-bundle` `^1.4`). Panel pages extend `panel/base.html.twig`, which loads `asset('css/nowo-ui.css', 'nowo_ui_kit')` and imports `@NowoUiKitBundle/macros/ui.html.twig` (panel index flashes via `ui.flash`). Extension implements `PrependExtensionInterface` and seeds `nowo_ui_kit` from `web_ui.css_framework` / `icon_set` when the host has not configured UiKit.
- **UiKit:** Panel templates use `ui.btn` / `ui.row_actions` macros with `nowo_maintenance_mode_css_framework` instead of hand-rolled `nowo-ui-btn` / Bootstrap button classes.

[1.4.0]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.4.0

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

[Unreleased]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.11.0...HEAD
[1.5.8]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.5.7...v1.5.8
[1.5.7]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.5.6...v1.5.7
[1.5.6]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.5.5...v1.5.6
[1.5.5]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.5.4...v1.5.5
[1.5.4]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.5.3...v1.5.4
[1.5.3]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.5.2...v1.5.3
[1.5.2]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.5.1...v1.5.2
[1.5.1]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.5.0...v1.5.1
[1.5.0]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.4.1...v1.5.0
[1.4.1]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.4.0...v1.4.1
[1.4.0]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.3.0...v1.4.0
[1.3.0]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.2.2...v1.3.0
[1.2.2]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.1.2...v1.2.0
[1.1.2]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.1.1...v1.1.2
[1.1.1]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/nowo-tech/MaintenanceModeBundle/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/nowo-tech/MaintenanceModeBundle/releases/tag/v1.0.0
