# Upgrading

## Table of contents

- [Unreleased](#unreleased)
- [To 1.11.0](#to-1110)
- [To 1.10.2](#to-1102)
- [To 1.10.1](#to-1101)
- [To 1.10.0](#to-1100)
  - [Template contract (1.10)](#template-contract-110)
- [To 1.9.0](#to-190)
- [To 1.8.0](#to-180)
- [To 1.7.1](#to-171)
- [To 1.7.0](#to-170)
- [To 1.6.1](#to-161)
- [To 1.6.0](#to-160)
- [To 1.5.9](#to-159)
- [To 1.5.8 (FrankenPHP worker / reset_kernel false)](#to-158-frankenphp-worker--reset_kernel-false)
- [From 1.5.6 to 1.5.7](#from-156-to-157)
- [To 1.5.6](#to-156)
- [To 1.5.5](#to-155)
- [To 1.5.4](#to-154)
- [To 1.5.3](#to-153)
- [To 1.5.2](#to-152)
- [To 1.5.1](#to-151)
- [To 1.5.0](#to-150)
  - [Panel Symfony Forms](#panel-symfony-forms)
  - [Breaking changes](#breaking-changes)
- [To 1.4.1](#to-141)
- [To 1.4.0](#to-140)
  - [Twig Extra Bundle (REQ-TWIG-004)](#twig-extra-bundle-req-twig-004)
  - [Twig-CS-Fixer (maintainers)](#twig-cs-fixer-maintainers)
- [To 1.3.0](#to-130)
  - [Requirements](#requirements)
  - [Install / update](#install--update)
  - [Behaviour change (panel roles)](#behaviour-change-panel-roles)
  - [New optional config](#new-optional-config)
  - [Breaking changes](#breaking-changes-1)
- [To 1.2.2](#to-122)
  - [Behaviour change (panel CSRF)](#behaviour-change-panel-csrf)
  - [Requirements](#requirements-1)
  - [Install / update](#install--update-1)
  - [Breaking changes](#breaking-changes-2)
- [To 1.2.1](#to-121)
  - [Requirements](#requirements-2)
  - [Install / update](#install--update-2)
  - [Behaviour notes (non-breaking)](#behaviour-notes-non-breaking)
  - [Breaking changes](#breaking-changes-3)
- [To 1.2.0](#to-120)
  - [Requirements](#requirements-3)
  - [Install / update](#install--update-3)
  - [New optional config](#new-optional-config-1)
  - [Behaviour notes (non-breaking)](#behaviour-notes-non-breaking-1)
  - [Breaking changes](#breaking-changes-4)
- [To 1.1.2](#to-112)
  - [Requirements](#requirements-4)
  - [Install / update](#install--update-4)
  - [Behaviour notes (non-breaking)](#behaviour-notes-non-breaking-2)
  - [Breaking changes](#breaking-changes-5)
- [To 1.1.1](#to-111)
  - [Requirements](#requirements-5)
  - [Install / update](#install--update-5)
  - [Behaviour notes (non-breaking)](#behaviour-notes-non-breaking-3)
  - [Breaking changes](#breaking-changes-6)
- [To 1.1.0](#to-110)
  - [Requirements](#requirements-6)
  - [Install / update](#install--update-6)
  - [Behaviour changes (non-breaking for most apps)](#behaviour-changes-non-breaking-for-most-apps)
  - [New optional config (defaults are safe)](#new-optional-config-defaults-are-safe)
  - [New console commands](#new-console-commands)
  - [After upgrading](#after-upgrading)
  - [Breaking changes](#breaking-changes-7)
- [To 1.0.0](#to-100)
  - [Requirements](#requirements-7)
  - [Install](#install)
  - [Breaking changes](#breaking-changes-8)
  - [Storage backends](#storage-backends)
  - [After upgrading](#after-upgrading-1)

## Unreleased

## To 1.11.0

From **1.10.2**:

```bash
composer update nowo-tech/maintenance-mode-bundle
```

- **New dependencies:** `symfony/clock`, `psr/clock` and `psr/log` are now required (Composer installs them). With FrameworkBundle, the `clock` and `logger` services are injected automatically; nothing to configure.
- **Logging:** maintenance state changes are logged at `info`, panel login failures / lockouts at `warning` (no operator names, IPs or secrets). Route them to a dedicated Monolog channel or level if they are too verbose for your production logs.
- **Tests that freeze time:** `MaintenanceManager`, `MaintenanceRequestSubscriber`, `MaintenancePreviewController` and `PasswordMaintenanceAccessGate` accept an optional `Psr\Clock\ClockInterface` (last constructor argument; `MaintenanceManager` and the gate also take a `LoggerInterface`). Existing positional arguments are unchanged.
- **Preview route:** `nowo_maintenance_mode_preview` is now a `#[Route]` attribute on `MaintenancePreviewController` (same name, same configurable path). Hosts importing `@NowoMaintenanceModeBundle/Resources/config/routes.yaml` need no change.

## To 1.10.2

From **1.10.1**:

```bash
composer update nowo-tech/maintenance-mode-bundle
```

No configuration change. The panel and login forms now render with the UiKit form theme
`@NowoUiKit/form/nowo_ui_layout.html.twig` (UiKit `input` / `input_full` slots on the message, schedule and password
fields), on top of your `twig.form_themes`. Hosts that styled those fields with their own selectors may see the UiKit
input styling (full-width textareas) instead; customise the `input` / `input_full` slots under
`nowo_ui_kit.theme.bundles.NowoMaintenanceModeBundle` rather than overriding the template.

## To 1.10.1

From **1.10.0**:

```bash
composer update nowo-tech/maintenance-mode-bundle
```

Requires UiKit **^2.2** (`composer require nowo-tech/ui-kit-bundle:^2.2`). No configuration change. What hosts may
notice:

- **Panel**: Disable maintenance / Clear schedule sit next to the section's submit button (UiKit
  `SettingsSection::actions`); the submit button is printed after `</form>` and bound with `form="<id>"`, and the
  panel forms have ids (`nowo-maintenance-enable-form`, `nowo-maintenance-schedule-form`, …). Overrides of
  `panel/index.html.twig` that printed `section_actions` themselves after `{{ parent() }}` now print them twice —
  drop that loop (the variable stays for one more minor).
- **Login**: no empty footer strip under the form (UiKit 2.2 auth scaffold).
- **History / status**: small uppercase table headers, subtle badges (`badge_info` follows the primary colour).
- Host guest layouts with their own UiKit script list: wrap it in `{% if nowo_ui_scripts_enabled ?? true %}` (or
  include `nowo_ui_partial('scripts')`) so the 503 stays JS-free; the bundle's 503 template already empties
  `nowo_ui_scripts`.

## To 1.10.0

From **1.9.0**:

```bash
composer update nowo-tech/maintenance-mode-bundle
```

From **1.9.0** — panel and public page on the UiKit 2.1 page contract (REQ-UI-003). Backward compatible for
configuration (deprecated keys keep working); hosts that **forked panel templates** must move to the UiKit theme or
to thin `web_ui.templates.<page>` overrides.

```bash
composer update nowo-tech/maintenance-mode-bundle nowo-tech/ui-kit-bundle
```

### Template contract (1.10)

What hosts may notice:

| Page | Before | After |
| --- | --- | --- |
| Panel `panel/index.html.twig` | own markup on `panel/base.html.twig`, `maintenance_*` blocks, `web_ui.classes` map | `@NowoUiKit/page/settings` (sections `status`, `manual`, `schedule`; Disable / Clear schedule as UiKit actions under their form; Logout as a header action); data block `nowo_ui_settings_section` |
| History `panel/history.html.twig` | own table, optional `web_ui.partials.pagination` | `@NowoUiKit/page/list` with UiKit pagination and empty state; data block `nowo_ui_list_row` |
| Login `panel/login.html.twig` | panel layout | `@NowoUiKit/page/auth` on `nowo_ui_kit.layouts.guest` (never `web_ui.layout_template`) |
| Public page `maintenance/page.html.twig` | standalone HTML + Google Fonts + inline CSS | unique page on `nowo_ui_kit.layouts.guest`, UiKit slots and tokens, no CDN / JS / session |

Layouts:

- `web_ui.layout_template: null` (new default) uses `nowo_ui_kit.layouts.admin`. Point that UiKit key at your layout
  or bridge once (it must render `nowo_ui_document_title`, `nowo_ui_head`, `nowo_ui_styles`, `nowo_ui_page_header`,
  `nowo_ui_flashes`, `nowo_ui_content`, `nowo_ui_modals`, `nowo_ui_scripts`; see UiKit `docs/PAGE_CONTRACT.md`).
- A 1.x layout set in `web_ui.layout_template` / `templates.panel_layout` that renders `{% block body %}` keeps
  working through `panel/_legacy_layout.html.twig` (runtime deprecation). The 1.x default
  `@NowoMaintenanceModeBundle/panel/layout.html.twig` is removed and maps to `null`.
- The public 503 uses `nowo_ui_kit.layouts.guest`. A host guest layout must stay cheap: no database, session or
  security calls (the page renders on `kernel.request` before the firewall). If rendering fails, the 503 serves the
  built-in minimal page.

Configuration (deprecated keys still work in 1.x, removed in 2.0; the canonical key wins when both are set):

| Deprecated | Replacement |
| --- | --- |
| `panel.enabled` / `panel.path_prefix` | `web_ui.enabled` / `web_ui.path_prefix` |
| `templates.panel_layout` | `web_ui.layout_template` |
| `templates.panel_index` / `panel_history` / `panel_login` | `web_ui.templates.index` / `history` / `login` |
| `web_ui.css_framework` / `web_ui.icon_set` | `nowo_ui_kit.css_framework` / `nowo_ui_kit.icon_set` |
| `web_ui.classes.<key>` | `nowo_ui_kit.theme.bundles.NowoMaintenanceModeBundle.classes.<slot>` (bridged, see below) |
| `web_ui.partials.*`, `show_page_header`, `document_title`, `test_ids` | no effect (UiKit partials / page header / titles; test ids always rendered) |

Note: `web_ui.enabled` used to have no effect; it now removes the panel routes when `false`.

`web_ui.classes` bridge (1.x key → UiKit slot; the value replaces the slot's framework defaults; values equal to
the 1.x default, empty values and `status_line` are dropped): `nav` → `tabs`, `nav_link` → `tab`, `page_header`,
`page_title`, `page_intro` → same, `flash` → `flash`, `status_on` → `badge_warning`, `status_off` → `badge_success`,
`card` → `card`, `section_title` → `card_title`, `form`, `form_actions`, `table_wrap`, `table`, `table_head_row`,
`table_head_cell`, `table_row`, `table_cell`, `table_cell_muted`, `empty` → same, `login_error` → `flash_error`.

Template overrides:

- Removed templates: `panel/base.html.twig`, `panel/layout.html.twig`, `panel/_nav.html.twig`,
  `panel/_nav_tabs.html.twig`, `panel/_form_fields.html.twig`. Forks of `panel/*.html.twig` under
  `templates/bundles/NowoMaintenanceModeBundle/` and their `maintenance_*` block overrides no longer apply: delete
  them, theme UiKit once, and use `web_ui.templates.<page>` with a template that extends
  `@NowoMaintenanceModeBundle/panel/<page>.html.twig` and overrides a data block for what is left.
- Panel context: `page` (view model) is new; `layout` is gone; `state`, `path_prefix`, `password_required`, the
  form views, `entries`, `error`, `locked`, `login_form` and `flashes` are kept.
- Public page overrides that extend `@NowoMaintenanceModeBundle/maintenance/page.html.twig` keep working; the
  `maintenance_head` / `maintenance_fonts` / `maintenance_styles` / `maintenance_head_extra` blocks render in
  `nowo_ui_head`, `maintenance_scripts` in `nowo_ui_scripts`. `maintenance_html_attributes` /
  `maintenance_body_attributes` are no longer rendered (use `nowo_ui_kit.theme.attributes.document` / `body`). The
  default no longer loads Google Fonts: set fonts through your theme tokens or `maintenance_fonts`.
- Test ids: `maintenance-panel-tabs`, `maintenance-status`, `maintenance-manual`, `maintenance-schedule`,
  `maintenance-history` are always rendered; new `maintenance-panel`, `maintenance-page`, `maintenance-login`,
  `maintenance-disable`, `maintenance-clear-schedule`, `maintenance-logout`.
- The panel index URL is `{path_prefix}/` (the area nav links to it; `{path_prefix}` still redirects there).

## To 1.9.0

From **1.8.0** — security - segment-aware exclusions.path_prefixes.

```bash
composer update nowo-tech/maintenance-mode-bundle
```

- **Behaviour change:** `exclusions.path_prefixes` entries now match whole path segments: `/api` exempts `/api` and `/api/…`, but `/apix` / `/api-docs` are now served the maintenance page. If you relied on raw prefix matching, append `*` to the entry (e.g. `/api*`) or add the extra prefixes explicitly.

## To 1.8.0

From **1.7.1** — security - panel login hardened (session migrate, throttling), HMAC bypass cookie, segment-aware panel prefix.

```bash
composer update nowo-tech/maintenance-mode-bundle
```

- Existing bypass cookies stop working (the cookie now stores an HMAC); visit once more with `?maintenance_bypass=TOKEN`.
- After `security.login_max_attempts` (default 5) wrong passwords within `security.login_lockout_seconds` (default 900) the panel login is locked for that session/IP (429). Set `login_max_attempts: 0` to disable.
- Paths such as `/_maintenance-foo` are no longer exempt from the 503.

## To 1.7.1

From **1.7.0** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/maintenance-mode-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.7.0

From **1.6.1** — admin chrome hooks (blocks, class map, partial paths). Opt-in; default output is byte-identical, except the logout bug fix below.

```bash
composer update nowo-tech/maintenance-mode-bundle
php bin/console cache:clear
```

- No breaking changes. Nothing to do unless you want to shrink page forks.
- **Visible change (bug fix):** the panel's logout button is no longer rendered when password protection is off (`security.password_protection: false` or no `password_hash`). It only cleared a gate session that did not exist. Forks that copied the old `password_required|default(true)` condition should switch to `password_required ?? true`.
- To drop a page fork, see [USAGE — Customising the admin chrome](USAGE.md#customising-the-admin-chrome): set `web_ui.classes.*`, `web_ui.partials.*`, `web_ui.show_page_header`, `web_ui.document_title`, `web_ui.test_ids`, and move shell-level header actions into your `layout_template` bridge.
- If you need a different structure for one section, point `templates.panel_*` at a host template **outside** `templates/bundles/NowoMaintenanceModeBundle/` that extends the bundle page and overrides a `maintenance_*` block. Delete the old fork at the bundle path first, otherwise the template extends itself.
- The bundle now ships `panel/_nav.html.twig`. An existing host file at `templates/bundles/NowoMaintenanceModeBundle/panel/_nav.html.twig` overrides it automatically (it receives `active` and `path_prefix`).
- New translation key `panel.nav.aria_label`. If you override the bundle catalogue, add it (English: `Panel`).

## To 1.6.1

From **1.6.0** — dependency refresh.

```bash
composer update nowo-tech/maintenance-mode-bundle
```

- No breaking changes. No application upgrade steps.

## To 1.6.0

From **1.5.9** — `access_roles` fail-closed.

```bash
composer update nowo-tech/maintenance-mode-bundle
php bin/console cache:clear
```

- Empty `security.access_roles` denies the manage UI. Set roles / custom checker, or demo-only `allow_unauthenticated`.

## To 1.5.9

From **1.5.8** — REQ-CS-008 Igor FrankenPHP worker audit (igor-php require-dev, igor.json, make igor).

```bash
composer update nowo-tech/maintenance-mode-bundle
php bin/console cache:clear
```

- No application upgrade steps for require-dev Igor tooling (REQ-CS-008). Consumers do not pull `igor-php/igor-php` transitively.

## To 1.5.8 (FrankenPHP worker / reset_kernel false)

Docs + regression tests confirming the bundle is **safe when the kernel is not reset** between FrankenPHP worker requests. **No application configuration or API changes.**

```bash
composer update nowo-tech/maintenance-mode-bundle
php bin/console cache:clear
```

See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md). Custom storage / access-gate / access-checker services must stay stateless (or implement `ResetInterface` if they memoize).

## From 1.5.6 to 1.5.7

No application upgrade steps (docs / style only).

```bash
composer update nowo-tech/maintenance-mode-bundle
php bin/console cache:clear
```

## To 1.5.6

Review Flex `when@prod` (preview off, `ROLE_ADMIN`) and `security_nowo_maintenance_mode.yaml`. Prefer **`^1.5.6`**.

```bash
composer update nowo-tech/maintenance-mode-bundle
php bin/console cache:clear
```

## To 1.5.5

No application upgrade steps.

```bash
composer update nowo-tech/maintenance-mode-bundle
php bin/console cache:clear
```

## To 1.5.4

No application upgrade steps. **Demos only:** Hot Reload Bundle `^1.4` (FrankenPHP Mercure/`hot_reload`, `dev`/`test`).

```bash
composer update nowo-tech/maintenance-mode-bundle
php bin/console cache:clear
```

## To 1.5.3

From **1.5.2** — Composer-only patch restoring `^7.4 || ^8.0` on core `symfony/*` packages that a Code Style auto-commit had narrowed again on `main` between 1.5.1 and 1.5.2. Prefer **1.5.3+** on Symfony **8**.

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.5.3
php bin/console cache:clear
```

## To 1.5.2

From **1.5.1** — maintainer / CI fix only: the Code Style GitHub Action restores `composer.json` and `composer.lock` after installing Symfony 7.4 for the job, so auto-commits no longer strip `|| ^8.0` from core constraints. Docs: INSTALLATION lists `symfony/form` + UiKit; USAGE documents panel FormViews.

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.5.2
php bin/console cache:clear
```

No application config changes.

## To 1.5.1

From **1.5.0** — Composer-only patch restoring `^7.4 || ^8.0` on core `symfony/*` packages that a CS Fixer bot commit had narrowed to `^7.4` only.

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.5.1
php bin/console cache:clear
```

## To 1.5.0

### Panel Symfony Forms

From **1.4.1** — panel mutations use Symfony Forms (`symfony/form`) instead of hand-rolled HTML + manual CSRF checks.

```bash
composer update nowo-tech/maintenance-mode-bundle
# ensure Form is available (usually already present via FrameworkBundle)
composer require "symfony/form:^7.4 || ^8.0"
php bin/console cache:clear
```

1. Bundle templates call `form_start` / `form_row` loop / `form_end` with FormViews: `enable_form`, `disable_form`, `schedule_form`, `clear_schedule_form`, `logout_form`, `login_form`.
2. CSRF token ids are unchanged (`nowo_maintenance_enable`, …). Field names stay flat (empty form block prefix).
3. Frozen host overrides of `panel/index.html.twig` / `panel/login.html.twig` that still use raw `<form>` + `csrf_token()` continue to work for POST as long as field names match; preferred upgrade is to copy the bundle Twig pattern (or include `@NowoMaintenanceModeBundle/panel/_form_fields.html.twig`).
4. CSRF-only actions (`disable`, `clear-schedule`, `logout`) now include a hidden `confirmed=1` field so Symfony treats the unnamed form as submitted. Raw overrides must post that field (or switch to `form_start` / FormViews).

### Breaking changes

- Runtime dependency on **`symfony/form`**.
- Twig context for the panel index/login now expects FormView variables (raw-only overrides that ignore them still render if you keep your own markup).

## To 1.4.1

From **1.4.0** — Composer-only patch: restore `^7.4 || ^8.0` on core `symfony/*` packages that `v1.4.0` had narrowed to `^7.4` only (`config`, `dependency-injection`, `http-foundation`, `http-kernel`, `security-core`). No code or config changes.

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.4.1
php bin/console cache:clear
```

If you stayed on **1.3.0** because Composer rejected `1.4.0` on Symfony **8.1**, jump straight to **1.4.1** (still includes UiKit / Twig Extra from 1.4.0 — see [To 1.4.0](#to-140)).

## To 1.4.0

From **1.3.0** — Adds UiKit composition, Twig Extra (REQ-TWIG-004), and Twig-CS-Fixer. Register TwigExtraBundle and NowoUiKitBundle if Flex did not. See CHANGELOG. **Note:** published `v1.4.0` was not installable on Symfony 8 — use **1.4.1+**.

```bash
composer update nowo-tech/maintenance-mode-bundle
php bin/console cache:clear
```

Panel UI now depends on **[UiKitBundle](https://github.com/nowo-tech/UiKitBundle)** (`nowo-tech/ui-kit-bundle` `^1.4`).

1. Require the package (pulled transitively once you update this bundle) and run `assets:install`.
2. Panel pages extend `panel/base.html.twig`, which stacks `asset('css/nowo-ui.css', 'nowo_ui_kit')` via `parent()`.
3. Optional: set `nowo_ui_kit.css_framework` / `icon_set` in the host. If unset, MaintenanceMode seeds those keys from `web_ui` (defaults `custom` / `none`).
4. Frozen overrides of `panel/index.html.twig` / `history` / `login` that extended the layout directly should switch to `@NowoMaintenanceModeBundle/panel/base.html.twig`.
5. Panel flash markup uses `ui.flash()` from `@NowoUiKitBundle/macros/ui.html.twig` (import in `panel/base.html.twig`).

### Twig Extra Bundle (REQ-TWIG-004)

Hosts that render this bundle's Twig templates must install:

```bash
composer require twig/extra-bundle twig/string-extra
```

and enable `Twig\Extra\TwigExtraBundle\TwigExtraBundle`. Flex recipes usually register it automatically.

### Twig-CS-Fixer (maintainers)

Package maintainers: `composer twig:lint` / `composer twig:fix` use `.twig-cs-fixer.php` over `src/` (and `templates/` when present).

## To 1.3.0

Minor release: REQ-UI-002 role-based panel access (`access_roles` / `access_checker`) plus restore of Symfony **8** Composer constraints. Default panel security is stricter.

### Requirements

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** still needs **PHP 8.4+**.
- **Symfony** `^7.4 || ^8.0` (CI minors: **7.4**, **8.0**, **8.1**).
- **SecurityBundle** when the panel is enabled and `security.allow_unauthenticated` is `false` (default).

### Install / update

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.3
php bin/console cache:clear
```

### Behaviour change (panel roles)

| Topic | Before | 1.3.0 |
| --- | --- | --- |
| Default panel auth | Ops password gate only | Symfony roles (`ROLE_ADMIN` by default) **plus** optional password gate |
| Apps without SecurityBundle | Panel could boot | Boot fails with `LogicException` unless `allow_unauthenticated: true` |

**Demos / trusted local kernels** without SecurityBundle:

```yaml
nowo_maintenance_mode:
    security:
        allow_unauthenticated: true   # never in production
```

**Production** (recommended): keep `allow_unauthenticated: false`, ensure SecurityBundle is installed, and grant at least one of `access_roles` (or provide a custom `access_checker`).

### New optional config

```yaml
nowo_maintenance_mode:
    security:
        access_roles: [ROLE_ADMIN]
        access_checker: null
        allow_unauthenticated: false
```

### Breaking changes

Apps that enabled the panel without SecurityBundle (or without a matching `access_roles` grant) must either install/configure SecurityBundle roles or set `allow_unauthenticated: true` for non-production use.

---

## To 1.2.2

Patch release: panel CSRF **fail-closed** (REQ-SEC-005).

### Behaviour change (panel CSRF)

Panel mutations are **fail-closed** when CSRF cannot be validated (REQ-SEC-005):

| Topic | Before | After |
| --- | --- | --- |
| `CsrfTokenManagerInterface` missing | Panel POSTs accepted without CSRF (fail-open) | Panel POSTs return **403** (fail-closed) |

**Requirement:** `symfony/security-csrf` must be available and FrameworkBundle CSRF enabled so `CsrfTokenManagerInterface` is registered. Without it, enable/disable/schedule/login/logout panel actions will always fail with HTTP 403.

Demos and minimal kernels that previously ran the panel without Security CSRF must enable CSRF (or install/enable `symfony/security-csrf` via FrameworkBundle) before using panel mutations.

### Requirements

Same as 1.2.1, plus working Framework CSRF (`symfony/security-csrf`).

### Install / update

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.2
php bin/console cache:clear
```

### Breaking changes

None for apps that already had CSRF enabled for the panel. Apps that relied on fail-open (no CSRF manager) will see **403** on panel mutations until CSRF is enabled.

---

## To 1.2.1

Patch release: restore Symfony **8** Composer constraints after a post-`v1.2.0` automation commit narrowed several packages back to `^7.4` only; README section reorder; panel layout Twig comment.

### Requirements

Same as 1.2.0:

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** still needs **PHP 8.4+**.
- **Symfony** `^7.4 || ^8.0` (CI / mandatory minors: **7.4**, **8.0**, **8.1**).

### Install / update

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.2
php bin/console cache:clear
```

If Composer rejects installs on Symfony **8.0** / **8.1** after pulling `v1.2.0` + later automation commits, upgrade to **1.2.1+**.

### Behaviour notes (non-breaking)

| Topic | Before (post-1.2.0 tree) | 1.2.1 |
| --- | --- | --- |
| Core `symfony/*` | Some pins back to `^7.4` only | All runtime packages `^7.4 \|\| ^8.0` again |

### Breaking changes

None.

---

## To 1.2.0

Minor release: optional panel look-and-feel (`web_ui`), demo smoke target, deprecation-strict PHPUnit, and docs/Make standards. Requirements are unchanged from 1.1.2.

### Requirements

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** still needs **PHP 8.4+**.
- **Symfony** `^7.4 || ^8.0` (CI / mandatory minors: **7.4**, **8.0**, **8.1**).

### Install / update

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.2
php bin/console cache:clear
```

### New optional config

```yaml
nowo_maintenance_mode:
    web_ui:
        enabled: true
        layout_template: 'base.html.twig'   # host project layout
        css_framework: bootstrap5           # or tailwind | foundation | custom | …
        icon_set: none
```

`web_ui.layout_template` stays in sync with legacy `templates.panel_layout` (whichever you set wins when the other is still the bundle default).

Twig globals: `nowo_maintenance_mode_layout_template`, `nowo_maintenance_mode_css_framework`, `nowo_maintenance_mode_icon_set`.

### Behaviour notes (non-breaking)

| Topic | Before | 1.2.0 |
| --- | --- | --- |
| Core `symfony/*` constraints | Some packages still `^7.4` only on the published tree | All runtime `symfony/*` use `^7.4 \|\| ^8.0` |
| Panel chrome | Bundle-only layout / classes | Semantic `nowo-ui-*` + optional host layout via `web_ui` |
| Demo smoke | `release-verify` loop | Explicit `make demo-smoke` (REQ-TEST-011) |
| PHPUnit deprecations | Default helper | `max[direct]=0` (REQ-SF-005) |

### Breaking changes

None for public PHP APIs or existing config keys.

---

## To 1.1.2

Patch release: Symfony **8** installability for core Composer constraints, and docs aligned to the supported floor (**7.4** / **8.0** / **8.1**).

### Requirements

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** still needs **PHP 8.4+**.
- **Symfony** `^7.4 || ^8.0` (CI / mandatory minors: **7.4**, **8.0**, **8.1**). Symfony **7.0–7.3** and **6.4** are not supported.

### Install / update

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.1
php bin/console cache:clear
```

If you are on Symfony **8.0** or **8.1** and Composer rejected `v1.1.1` for `symfony/config` (or related) packages, upgrade to **1.1.2+**.

### Behaviour notes (non-breaking)

| Topic | Before (1.1.1) | 1.1.2 |
| --- | --- | --- |
| Core `symfony/*` constraints | `^7.4` only on config / DI / http-* / security-core | `^7.4 \|\| ^8.0` — installable on Symfony 8 |
| Documented SF 7 floor | Mixed messaging (7.0–7.3 vs 7.4) | Explicit floor **7.4** |

### Breaking changes

None for public PHP APIs or config keys.

---

## To 1.1.1

Patch release: preview `default_message` null/empty handling for Twig translations, recipe preview defaults documented, and Nowo standards/docs scaffolding. Requirements are unchanged from 1.1.0 (Symfony 8 apps should use **1.1.2+** — see above).

### Requirements

Same as 1.1.0 / 1.0.0:

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** still needs **PHP 8.4+**.
- **Symfony** floor **7.4** (CI minors: **7.4**, **8.0**, **8.1**).

### Install / update

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.1
php bin/console cache:clear
```

### Behaviour notes (non-breaking)

| Topic | Before | 1.1.1 |
| --- | --- | --- |
| `default_message` + preview | Empty/`null` could leave an empty string in the Twig context | `null` and `''` become `null` so `\|default(...)` / `maintenance.page.message` work |
| Recipe / sample YAML | Preview keys often omitted | Documents `preview.enabled` / `preview.path` |

Optional — rely on translations for the public page body:

```yaml
nowo_maintenance_mode:
    default_message: null
```

### Breaking changes

None.

---

## To 1.1.0

Minor release: new ops CLI, exclusions, preview route, events, Twig helpers, and page examples. Requirements are unchanged.

### Requirements

Same as 1.0.0:

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** still needs **PHP 8.4+**.
- **Symfony** floor **7.4** (CI minors: **7.4**, **8.0**, **8.1**).

### Install / update

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.1
php bin/console cache:clear
```

Ensure routes still import `@NowoMaintenanceModeBundle/Resources/config/routes.yaml` (panel + **`/_maintenance_preview`**).

### Behaviour changes (non-breaking for most apps)

| Topic | 1.0.0 | 1.1.0 |
| --- | --- | --- |
| Default `subscriber_priority` | `32` | `31` (after the router so route / `#[ExcludeFromMaintenance]` work) |
| Request listener registration | `EventSubscriberInterface` + tag priority was easy to miss | `kernel.event_listener` with configured priority |
| Public 503 body | HTML only | HTML or JSON when the client prefers JSON |
| `Retry-After` | Fixed `retry_after` | Uses remaining time until `scheduledDisableAt` when set |

If you relied on the listener running **before** the router, set explicitly:

```yaml
nowo_maintenance_mode:
    subscriber_priority: 32   # or higher
```

### New optional config (defaults are safe)

```yaml
nowo_maintenance_mode:
    preview:
        enabled: ~                    # null = kernel.debug
        path: '/_maintenance_preview'
    exclusions:
        ips: []
    security:
        bypass_token: null
        # bypass_query_parameter / bypass_cookie_name / bypass_set_cookie
```

### New console commands

```bash
php bin/console nowo:maintenance-mode:hash-password
php bin/console nowo:maintenance-mode:enable -m "Deploy" --until="2026-07-24T18:00:00+00:00"
php bin/console nowo:maintenance-mode:status   # exit 2 when effectively on
php bin/console nowo:maintenance-mode:disable
```

### After upgrading

1. `php bin/console cache:clear`
2. In **dev**, open `/_maintenance_preview` (like `/_error/503`) to verify `templates.page`.
3. Smoke-test `/_maintenance` and a non-excluded public route with maintenance enabled (expect HTTP **503**).
4. If you use custom listeners on maintenance state, subscribe to the new domain events as needed.

### Breaking changes

None for public PHP APIs. Config keys from 1.0.0 remain valid.

---

## To 1.0.0

First public release. No prior Packagist versions.

### Requirements

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** still needs **PHP 8.4+**.
- **Symfony** floor **7.4** (mandatory minors in CI: **7.4**, **8.0**, **8.1**). Symfony **6.4** is not supported.

### Install

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.0
```

With **Symfony Flex**, the recipe registers the bundle and adds config. Without Flex:

1. Register `Nowo\MaintenanceModeBundle\NowoMaintenanceModeBundle` in `config/bundles.php`.
2. Import routes: `@NowoMaintenanceModeBundle/Resources/config/routes.yaml` (or the recipe file under `config/routes/`).
3. Ensure `var/maintenance/` is writable (or override `storage.state_file` / `history_file`).
4. Prefer a hashed panel password via env: `password_hash: '%env(MAINTENANCE_PASSWORD_HASH)%'` (never store plaintext).

### Breaking changes

None (initial release).

### Storage backends

Switching from filesystem to a custom Doctrine (or other) backend: implement the storage interfaces and set `storage.state_storage` / `storage.history_storage`. Migrate existing JSON/JSONL files manually if you need history continuity.

### After upgrading

```bash
php bin/console cache:clear
```

Smoke-test `/_maintenance` and a non-excluded public route while maintenance is enabled (expect HTTP **503**).
