# Usage

## Table of contents

- [Public maintenance page](#public-maintenance-page)
  - [Attribute / route default](#attribute--route-default)
  - [Calm page examples](#calm-page-examples)
- [Dev preview](#dev-preview-like-_error503)
- [Admin panel](#admin-panel)
  - [Look-and-feel (REQ-UI-001)](#look-and-feel-req-ui-001)
- [Customising the admin chrome](#customising-the-admin-chrome)
- [CLI](#cli-deploy--ops)
- [Programmatic API](#programmatic-api)
  - [Twig](#twig)
- [Custom storage / access gate](#custom-storage--access-gate)
- [Twig overrides](#twig-overrides)

## Public maintenance page

When maintenance is **effectively enabled** (manual flag or schedule window), `MaintenanceRequestSubscriber` intercepts main HTTP requests and returns **HTTP 503** with a `Retry-After` header (and `Cache-Control: no-store`). Clients that prefer JSON receive a JSON body (`status`, `message`, `retry_after`, `scheduled_disable_at`).

Excluded requests (exact paths, prefixes, route names, globs / `#regex#`, **IPs/CIDR**, soft bypass token, `#[ExcludeFromMaintenance]`, route default `_maintenance_exclude`) and the admin panel prefix are never blocked.

**Do not** put a link to `/_maintenance/login` on the public 503 page. Operators should reach the panel via the known panel URL (auto-excluded) or other excluded ops paths. Toggle the login UI with YAML:

```yaml
nowo_maintenance_mode:
    security:
        access_roles: [ROLE_ADMIN]
        access_checker: null
        allow_unauthenticated: false   # true only for local demos without SecurityBundle
        password_protection: false   # no login section; panel opens directly
        # password_protection: true
        # password_hash: '%env(MAINTENANCE_PASSWORD_HASH)%'
        # Soft QA bypass (optional):
        # bypass_token: '%env(MAINTENANCE_BYPASS_TOKEN)%'
    exclusions:
        paths: ['/health']
        path_prefixes: ['/api/ops', '/examples']
        routes: ['app_status']
        patterns: ['/internal-*', '#^/ops/#']
        ips: ['127.0.0.1', '10.0.0.0/8']
```

When `allow_unauthenticated` is `false` (default), the panel requires `symfony/security-bundle` and at least one of `access_roles` (or a custom `access_checker`). The ops **password gate** remains an **additional** layer when `password_protection` is true.

See the FrankenPHP demo guide at `/examples/bypass` for live smoke links.

### Attribute / route default

```php
use Nowo\MaintenanceModeBundle\Attribute\ExcludeFromMaintenance;

#[ExcludeFromMaintenance]
final class HealthController
{
    public function __invoke(): Response { /* … */ }
}

// Or on a single action:
#[ExcludeFromMaintenance]
public function ping(): Response { /* … */ }

// Or via route defaults:
#[Route('/ready', defaults: [ExcludeFromMaintenance::ROUTE_DEFAULT => true])]
```

Override the page template:

```twig
{# templates/bundles/NowoMaintenanceModeBundle/maintenance/page.html.twig #}
```

Or set `templates.page` in config.

### Calm page examples

The bundle ships several ready-made 503 templates (Bootstrap 5.3, Foundation 6.9, Tailwind CSS 4, plus six “idea” patterns: short, compassionate, playful, brand, countdown, updates) with friendly multi-locale copy. See [MAINTENANCE-PAGE-EXAMPLES.md](MAINTENANCE-PAGE-EXAMPLES.md).

```yaml
nowo_maintenance_mode:
    templates:
        page: '@NowoMaintenanceModeBundle/maintenance/examples/tailwind_breeze.html.twig'
```

## Dev preview (like `/_error/503`)

In the **dev** environment (`kernel.debug`), open the currently configured public maintenance template without enabling maintenance:

```bash
# HTML (HTTP 503 + X-Maintenance-Preview: 1)
open http://localhost:8055/_maintenance_preview

# Optional message override
open 'http://localhost:8055/_maintenance_preview?message=Deploy%20window'

# JSON
curl -H 'Accept: application/json' http://localhost:8055/_maintenance_preview
```

Disable or relocate:

```yaml
nowo_maintenance_mode:
    preview:
        enabled: false          # or true to force on outside debug
        path: '/_maintenance_preview'
```

## Admin panel

Default URL prefix: `/_maintenance`.

| Route | Method | Purpose |
| --- | --- | --- |
| `/_maintenance` | GET | Status + enable / disable / schedule forms |
| `/_maintenance/enable` | POST | Enable maintenance |
| `/_maintenance/disable` | POST | Disable maintenance |
| `/_maintenance/schedule` | POST | Set scheduled enable/disable timestamps |
| `/_maintenance/clear-schedule` | POST | Clear both schedule timestamps |
| `/_maintenance/history` | GET | Append-only history |
| `/_maintenance/login` | GET/POST | Password gate |
| `/_maintenance/logout` | POST | Clear panel session |

If `security.password_protection` is `false`, or `security.password_hash` is empty, the panel does **not** show a login form — enable/disable is available immediately under the panel prefix.

Panel mutations use Symfony Forms (`Nowo\MaintenanceModeBundle\Form\*`). Bundle Twig calls `form_start` / `form_end` and includes `@NowoMaintenanceModeBundle/panel/_form_fields.html.twig`, which loops `form_row` for every child that is **not** already rendered. Host overrides of `panel/index.html.twig` / `panel/login.html.twig` should prefer the FormView variables (`enable_form`, `disable_form`, `schedule_form`, `clear_schedule_form`, `logout_form`, `login_form`) or keep posting the same flat field names + `_token` (and `confirmed=1` on CSRF-only actions). See [UPGRADING](UPGRADING.md#to-150).

### Look-and-feel (REQ-UI-001)

Point the panel at your project chrome and CSS stack:

```yaml
nowo_maintenance_mode:
    web_ui:
        layout_template: 'base.html.twig'   # project layout (stylesheets / javascripts with parent())
        css_framework: bootstrap5           # or: tailwind | foundation | custom
        icon_set: none
```

Twig globals: `nowo_maintenance_mode_layout_template`, `nowo_maintenance_mode_css_framework`, `nowo_maintenance_mode_icon_set`, `nowo_maintenance_mode_chrome` (1.7.0). Panel markup uses semantic `nowo-ui-*` classes so hosts can restyle without forking every template; see [Customising the admin chrome](#customising-the-admin-chrome) for the class map, partial paths and blocks.

Generate a hash for `password_hash` / `MAINTENANCE_PASSWORD_HASH`:

```bash
php bin/console nowo:maintenance-mode:hash-password
php bin/console nowo:maintenance-mode:hash-password --algo=argon2id
```

Prefer the interactive prompt (no argument) so the plaintext password is not stored in shell history.

## Customising the admin chrome

*Since 1.7.0.* Hosts can theme the panel (and, if they want, the public page) **without forking whole pages**: a `layout_template` bridge plus config covers most cases, and Twig blocks cover the rest. Defaults render exactly the pre-1.7.0 markup (byte-identical, guarded by render tests), except that the logout button is now hidden when no password gate is active (bug fix).

### 1. Config only (class map, partials, flags)

```yaml
nowo_maintenance_mode:
    web_ui:
        layout_template: 'admin/maintenance_layout.html.twig'   # host shell (sidebar, page title, header actions)
        css_framework: tailwind                                  # ui.btn / ui.flash / UiKit tabs follow it
        show_page_header: false     # host layout already prints the title
        document_title: false       # keep the host layout's <title> block (parent())
        test_ids: true              # data-testid="maintenance-status|manual|schedule|history|panel-tabs"
        classes:
            card: 'panel mb-6'
            flash: 'mb-4'
            section_title: 'nowo-ui-section-title'
            form: 'nowo-ui-form--stack'
            form_actions: 'nowo-ui-form-actions'
            table_wrap: 'panel overflow-x-auto'
            table: 'data-table w-full text-left text-sm'
            table_head_cell: 'px-2 py-2 font-medium'
            table_row: 'row-hover'
            table_cell: 'px-2 py-3'
            empty: 'nowo-ui-empty px-2 py-3'
        partials:
            nav: '@NowoMaintenanceModeBundle/panel/_nav_tabs.html.twig'   # UiKit tabs with aria-current
            # nav: 'admin/maintenance_nav.html.twig'                      # or your own (receives active, path_prefix)
            # pagination: 'admin/_pagination.html.twig'                   # receives entries, path_prefix
```

Every key and default is listed in [CONFIGURATION — `web_ui.classes` defaults](CONFIGURATION.md#web_uiclasses-defaults-170). Empty string / `null` drops the `class` attribute. The Twig global `nowo_maintenance_mode_chrome` exposes the resolved `show_page_header`, `document_title`, `test_ids`, `classes` and `partials`.

### 2. Blocks (extend a bundle page from a host template)

Point `templates.panel_index` / `panel_history` / `panel_login` / `page` at a host template **outside** `templates/bundles/NowoMaintenanceModeBundle/` and extend the bundle page:

```yaml
nowo_maintenance_mode:
    templates:
        panel_index: 'admin/maintenance_index.html.twig'
```

```twig
{# templates/admin/maintenance_index.html.twig #}
{% extends '@NowoMaintenanceModeBundle/panel/index.html.twig' %}

{% block maintenance_status %}
    <dl class="maintenance-status">…</dl>
{% endblock %}
{% block maintenance_state_details %}{% endblock %}
```

> Do not keep a fork at `templates/bundles/NowoMaintenanceModeBundle/panel/index.html.twig` while extending `@NowoMaintenanceModeBundle/panel/index.html.twig`: the host override wins the namespace and the template would extend itself.

All block names start with `maintenance_` so they never clash with host layout blocks such as `page_title`, `body` or `content`.

| Template | Blocks (outer → inner) |
| --- | --- |
| `panel/index.html.twig` | `maintenance_nav`, `maintenance_page_header` (→ `maintenance_page_title`, `maintenance_page_intro`, `maintenance_header_actions`), `maintenance_flashes`, `maintenance_status`, `maintenance_state_details`, `maintenance_manual_forms`, `maintenance_schedule_forms`, `maintenance_logout_form` |
| `panel/history.html.twig` | `maintenance_nav`, `maintenance_page_header` (→ `maintenance_page_title`, `maintenance_page_intro`, `maintenance_header_actions`), `maintenance_toolbar`, `maintenance_table_wrapper` (→ `maintenance_table` → `maintenance_table_head` (→ `maintenance_table_head_actions`), `maintenance_table_row` (→ `maintenance_table_row_actions`; `entry` in scope), `maintenance_empty_state`), `maintenance_pagination` |
| `panel/login.html.twig` | `maintenance_page_header` (→ `maintenance_page_title`, `maintenance_header_actions`), `maintenance_page_intro`, `maintenance_login_error`, `maintenance_login_form` |
| `maintenance/page.html.twig` | `maintenance_html_attributes`, `title`, `maintenance_head` (→ `maintenance_fonts`, `maintenance_styles`), `maintenance_head_extra`, `maintenance_body_attributes`, `maintenance_body` (→ `maintenance_brand`, `maintenance_heading`, `maintenance_message`, `maintenance_reassure`, `maintenance_footer`), `maintenance_scripts` |

Empty-by-default blocks (`maintenance_page_intro` on panel pages, `maintenance_header_actions`, `maintenance_toolbar`, `maintenance_table_head_actions`, `maintenance_table_row_actions`, `maintenance_head_extra`, `maintenance_scripts`, the attribute blocks) render nothing until a host fills them. When you add a row-actions cell, also add the header cell and override `maintenance_empty_state` with `colspan="5"`.

Since 1.7.0 `maintenance_logout_form` renders only when a password gate is active (`password_required ?? true`); before, Twig's `default(true)` treated `false` as empty and the logout button showed even without a gate.

### 3. Layout bridge

Page title, intro and header actions that belong to the host shell (for example a "Preview" link next to the title) go in the `layout_template` bridge, not in a page fork: set your shell variables at the top of the bridge template, and keep `{% block body %}` for the bundle page.

### Worked example: symfony-beacon forks (1.6.1 → 1.7.0)

| Beacon fork | Verdict | How |
| --- | --- | --- |
| `panel/history.html.twig` | **Delete** | Config only: `show_page_header: false`, `document_title: false` (else the bundle's `{% block title %}` replaces the bridge's "Maintenance — Beacon"), `test_ids: true`, `classes.table_wrap: 'panel overflow-x-auto'`, `table: 'data-table w-full text-left text-sm nowo-ui-table'`, `table_head_row`, `table_head_cell: 'px-2 py-2 font-medium'`, `table_row`, `table_cell: 'px-2 py-3'`, `table_cell_muted`, `empty: 'nowo-ui-empty px-2 py-3'`. The "Preview" header action (`kit_admin_header_actions`) moves into `kit/maintenance_mode_panel_layout.html.twig`. Only difference left: wrapper element is `<div>` instead of `<section>`. |
| `panel/_nav.html.twig` | **Config only** (move) | The bundle now ships `panel/_nav.html.twig`, so a fork at that path silently shadows it (still works). Cleaner: move it to `templates/kit/maintenance_mode_nav.html.twig` and set `web_ui.partials.nav`. It receives `active` and `path_prefix`, so the `_route` sniffing can go. (Or use the shipped `_nav_tabs.html.twig` if Beacon's `shared/_tabs` extras — `nav_class`, `data-testid` — are not needed.) |
| `panel/index.html.twig` | **Shrink** to `{% extends '@NowoMaintenanceModeBundle/panel/index.html.twig' %}` + blocks | Move to `templates/kit/maintenance_mode_panel_index.html.twig`, set `templates.panel_index`, delete the `templates/bundles/` copy. Config: `show_page_header: false`, `document_title: false`, `test_ids: true`, `classes.flash: 'mb-4'`, `card: 'panel mb-6'`, `section_title: 'nowo-ui-section-title'`, `form: 'nowo-ui-form--stack'`, `form_actions: 'nowo-ui-form-actions'`, `status_on` / `status_off` without the legacy `status on|off`. Override `maintenance_status` (status + details as one `<dl>` grid), empty `maintenance_state_details`, `maintenance_manual_forms` / `maintenance_schedule_forms` (one action row per card, posting the companion form with `form="…"`; product layout choice). No logout override needed: with `password_protection: false` the bundle no longer renders the logout button (1.7.0 fix). The "Preview" header action moves to the layout bridge. |
| `maintenance/page.html.twig` | **Must stay** | Product branding, not admin chrome: Beacon theme boot + toggle, Vite entries, CSP-nonce styles, brand mark, 503 illustration shared with the error pages, scheduled-end countdown. The new public-page blocks would let it extend the bundle page, but it would override `maintenance_head`, `maintenance_body` and `maintenance_scripts` and the attribute blocks, about 95% of the document, so a full fork stays clearer. |

## CLI (deploy / ops)

```bash
php bin/console nowo:maintenance-mode:enable -m "Deploy in progress" --until="2026-07-24T18:00:00+00:00"
php bin/console nowo:maintenance-mode:status   # exit 2 when effectively on (handy in scripts)
php bin/console nowo:maintenance-mode:disable
```

## Programmatic API

```php
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;

public function __construct(private MaintenanceManager $maintenance) {}

public function takeDown(): void
{
    $this->maintenance->enable('Deploy in progress', 'ops');
}

public function bringUp(): void
{
    $this->maintenance->disable('ops');
}
```

Listen to `MaintenanceEnabledEvent`, `MaintenanceDisabledEvent`, or `MaintenanceUpdatedEvent` for side effects (metrics, Slack, cache purge).

### Twig

```twig
{% if nowo_maintenance_is_enabled() %}
  {# banner / soft notice while ops still browse excluded paths #}
{% endif %}
```

## Custom storage / access gate

Replace services via config:

```yaml
nowo_maintenance_mode:
    storage:
        state_storage: App\Maintenance\DoctrineStateStorage
        history_storage: App\Maintenance\DoctrineHistoryStorage
    security:
        access_gate: App\Maintenance\SecurityVoterGate
        # Or custom REQ-UI-002 checker:
        # access_checker: App\Maintenance\MyAccessChecker
```

Implement:

- `Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface`
- `Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface`
- `Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface`
- `Nowo\MaintenanceModeBundle\Security\MaintenanceModeAccessCheckerInterface` (optional custom `access_checker`)

## Twig overrides

Application templates under `templates/bundles/NowoMaintenanceModeBundle/` win over bundle views (REQ-TWIG-001). Namespace: `NowoMaintenanceModeBundle` (REQ-TWIG-002).

| Logical name | Default path |
| --- | --- |
| `@NowoMaintenanceModeBundle/maintenance/page.html.twig` | Public 503 page (default calm style) |
| `@NowoMaintenanceModeBundle/maintenance/examples/*.html.twig` | Optional framework examples (see [MAINTENANCE-PAGE-EXAMPLES.md](MAINTENANCE-PAGE-EXAMPLES.md)) |
| `@NowoMaintenanceModeBundle/panel/layout.html.twig` | Panel layout |
| `@NowoMaintenanceModeBundle/panel/index.html.twig` | Panel dashboard |
| `@NowoMaintenanceModeBundle/panel/login.html.twig` | Panel login |
| `@NowoMaintenanceModeBundle/panel/history.html.twig` | History table |
| `@NowoMaintenanceModeBundle/panel/_nav.html.twig` | Panel / History nav (1.7.0; configurable via `web_ui.partials.nav`) |
| `@NowoMaintenanceModeBundle/panel/_nav_tabs.html.twig` | Opt-in UiKit tabs nav (1.7.0) |

Or set `templates.*` / `web_ui.layout_template` keys in config.

Translation domain: `NowoMaintenanceModeBundle`. Locales shipped: **en, es, it, fr, pt, de, nl** (REQ-I18N-002). Calm maintenance page examples use the same domain under `maintenance.examples.*`.
