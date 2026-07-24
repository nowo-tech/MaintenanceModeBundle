# Usage

## Public maintenance page

When maintenance is **effectively enabled** (manual flag or schedule window), `MaintenanceRequestSubscriber` intercepts main HTTP requests and returns **HTTP 503** with a `Retry-After` header.

Excluded requests (exact paths, prefixes, route names, globs / `#regex#`) and the admin panel prefix are never blocked.

Override the page template:

```twig
{# templates/bundles/NowoMaintenanceModeBundle/maintenance/page.html.twig #}
```

Or set `templates.page` in config.

## Admin panel

Default URL prefix: `/_maintenance`.

| Route | Method | Purpose |
| --- | --- | --- |
| `/_maintenance` | GET | Status + enable / disable / schedule forms |
| `/_maintenance/enable` | POST | Enable maintenance |
| `/_maintenance/disable` | POST | Disable maintenance |
| `/_maintenance/schedule` | POST | Set scheduled enable/disable timestamps |
| `/_maintenance/history` | GET | Append-only history |
| `/_maintenance/login` | GET/POST | Password gate |
| `/_maintenance/logout` | POST | Clear panel session |

If `security.password_hash` is empty (or `password_protection: false`), the panel does not require a password.

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

## Custom storage / access gate

Replace services via config:

```yaml
nowo_maintenance_mode:
    storage:
        state_storage: App\Maintenance\DoctrineStateStorage
        history_storage: App\Maintenance\DoctrineHistoryStorage
    security:
        access_gate: App\Maintenance\SecurityVoterGate
```

Implement:

- `Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface`
- `Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface`
- `Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface`

## Twig overrides

Application templates under `templates/bundles/NowoMaintenanceModeBundle/` win over bundle views (REQ-TWIG-001). Namespace: `NowoMaintenanceModeBundle` (REQ-TWIG-002).

| Logical name | Default path |
| --- | --- |
| `@NowoMaintenanceModeBundle/maintenance/page.html.twig` | Public 503 page |
| `@NowoMaintenanceModeBundle/panel/layout.html.twig` | Panel layout |
| `@NowoMaintenanceModeBundle/panel/index.html.twig` | Panel dashboard |
| `@NowoMaintenanceModeBundle/panel/login.html.twig` | Panel login |
| `@NowoMaintenanceModeBundle/panel/history.html.twig` | History table |

Or set `templates.*` keys in config.

Translation domain: `NowoMaintenanceModeBundle`. Locales shipped: **en, es, it, fr, pt, de, nl** (REQ-I18N-002).
