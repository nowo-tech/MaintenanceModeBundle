# Usage

## Table of contents

- [Public maintenance page](#public-maintenance-page)
  - [Attribute / route default](#attribute--route-default)
  - [Calm page examples](#calm-page-examples)
- [Dev preview](#dev-preview-like-_error503)
- [Admin panel](#admin-panel)
  - [Look-and-feel (REQ-UI-001)](#look-and-feel-req-ui-001)
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

Twig globals: `nowo_maintenance_mode_layout_template`, `nowo_maintenance_mode_css_framework`, `nowo_maintenance_mode_icon_set`. Panel markup uses semantic `nowo-ui-*` classes so hosts can restyle without forking every template.

Generate a hash for `password_hash` / `MAINTENANCE_PASSWORD_HASH`:

```bash
php bin/console nowo:maintenance-mode:hash-password
php bin/console nowo:maintenance-mode:hash-password --algo=argon2id
```

Prefer the interactive prompt (no argument) so the plaintext password is not stored in shell history.

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

Or set `templates.*` / `web_ui.layout_template` keys in config.

Translation domain: `NowoMaintenanceModeBundle`. Locales shipped: **en, es, it, fr, pt, de, nl** (REQ-I18N-002). Calm maintenance page examples use the same domain under `maintenance.examples.*`.
