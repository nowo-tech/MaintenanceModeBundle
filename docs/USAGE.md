# Usage

## Table of contents

- [Public maintenance page](#public-maintenance-page)
  - [Attribute / route default](#attribute--route-default)
  - [Calm page examples](#calm-page-examples)
- [Dev preview](#dev-preview-like-_error503)
- [Admin panel](#admin-panel)
  - [Look-and-feel (REQ-UI-003)](#look-and-feel-req-ui-003)
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

The default page renders on the UiKit **guest layout** (`nowo_ui_kit.layouts.guest`) with your theme tokens: no
CDN, no JavaScript, no session, no database. If the template fails (for example a host layout that queries a
database that is down), the 503 falls back to a minimal built-in page instead of a 500 — check your template with
the [dev preview](#dev-preview-like-_error503), which shows the error.

Customise it with `templates.page` and a host template that extends the bundle page and overrides its
`maintenance_*` blocks:

```twig
{# templates/maintenance/503.html.twig — nowo_maintenance_mode.templates.page: 'maintenance/503.html.twig' #}
{% extends '@NowoMaintenanceModeBundle/maintenance/page.html.twig' %}
{% block maintenance_heading %}<h1>Acme is upgrading</h1>{% endblock %}
{% block maintenance_styles %}<link rel="stylesheet" href="{{ asset('css/maintenance.css') }}">{% endblock %}
```

Or extend `@NowoUiKit/page/auth.html.twig` (the page receives an `AuthPage` as `page`), or write a standalone
template (it receives `message` and `state`). Keep it free of database, session and security calls: the page renders
on `kernel.request`, before the firewall.

### Calm page examples

The bundle ships several **sample** 503 templates — opt-in, they load their framework and fonts from CDNs (Bootstrap 5.3, Foundation 6.9, Tailwind CSS 4, plus six “idea” patterns: short, compassionate, playful, brand, countdown, updates) with friendly multi-locale copy. Copy one into your app for production. See [MAINTENANCE-PAGE-EXAMPLES.md](MAINTENANCE-PAGE-EXAMPLES.md).

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

Panel mutations use Symfony Forms (`Nowo\MaintenanceModeBundle\Form\*`, CSRF-protected, flat field names).

### Look-and-feel (REQ-UI-003)

*Since 1.10* the panel pages follow the UiKit 2.1 page contract: the panel is a `page/settings` scaffold (status,
manual control, schedule), the history a `page/list` (paginated) and the password login a `page/auth` on the guest
layout. Theme them **once** for every Nowo bundle in UiKit:

```yaml
nowo_ui_kit:
    css_framework: bootstrap5
    layouts:
        admin: 'layouts/admin.html.twig'   # your layout / bridge rendering the nowo_ui_* blocks
        guest: 'layouts/guest.html.twig'
    theme:
        tokens: { primary: '#0b5fff', radius: '0.5rem' }
        bundles:
            NowoMaintenanceModeBundle: { classes: { card: 'shadow-sm' } }   # optional exception

nowo_maintenance_mode:
    web_ui:
        path_prefix: '/_maintenance'
        layout_template: null              # null = nowo_ui_kit.layouts.admin
```

The page → scaffold map, view models, data blocks and test ids are in [TEMPLATES.md](TEMPLATES.md). The panel
area navigation (Panel | History) is also published to the UiKit area-nav registry as `maintenance_mode`
(`nowo_ui_area_nav('maintenance_mode')`), so DashboardMenu or a host menu can mount it.

Generate a hash for `password_hash` / `MAINTENANCE_PASSWORD_HASH`:

```bash
php bin/console nowo:maintenance-mode:hash-password
php bin/console nowo:maintenance-mode:hash-password --algo=argon2id
```

Prefer the interactive prompt (no argument) so the plaintext password is not stored in shell history.

## Customising the admin chrome

The 1.7 chrome hooks (`web_ui.classes`, `web_ui.partials`, `show_page_header`, `document_title`, `test_ids`, the
`maintenance_*` blocks of the panel pages) are replaced by the UiKit theme and scaffolds since 1.10:

1. **Theme once** — `nowo_ui_kit.theme.classes` / `attributes` / `partials` / `tokens` restyle every bundle; use
   `nowo_ui_kit.theme.bundles.NowoMaintenanceModeBundle` for a panel-only exception. A deprecated `web_ui.classes`
   map is bridged there automatically for one minor version.
2. **Scaffold overrides** — `templates/bundles/NowoUiKitBundle/page/settings.html.twig` (or `list`, `auth`) re-skins
   that view type for all bundles.
3. **Per page** — `web_ui.templates.index|history|login`: a host template that extends
   `@NowoMaintenanceModeBundle/panel/<page>.html.twig` and overrides a data block (`nowo_ui_settings_section`,
   `nowo_ui_list_row`, `nowo_ui_list_after`, …).

The public page keeps its 1.7 `maintenance_*` blocks (see [TEMPLATES.md — Unique pages](TEMPLATES.md#unique-pages)).
Migration notes: [UPGRADING — Template contract (1.10)](UPGRADING.md#template-contract-110).

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
| `@NowoMaintenanceModeBundle/maintenance/page.html.twig` | Public 503 page (UiKit guest layout, unique page) |
| `@NowoMaintenanceModeBundle/maintenance/examples/*.html.twig` | Opt-in **samples** that load CDNs (see [MAINTENANCE-PAGE-EXAMPLES.md](MAINTENANCE-PAGE-EXAMPLES.md)) |
| `@NowoMaintenanceModeBundle/panel/index.html.twig` | Panel (`page/settings`) |
| `@NowoMaintenanceModeBundle/panel/history.html.twig` | History (`page/list`) |
| `@NowoMaintenanceModeBundle/panel/login.html.twig` | Password login (`page/auth`) |

Prefer config over `templates/bundles/` forks: `templates.page` for the public page and `web_ui.templates.<page>`
for the panel pages (thin templates that extend the bundle page). See [TEMPLATES.md](TEMPLATES.md).

Translation domain: `NowoMaintenanceModeBundle`. Locales shipped: **en, es, it, fr, pt, de, nl** (REQ-I18N-002). Calm maintenance page examples use the same domain under `maintenance.examples.*`.
