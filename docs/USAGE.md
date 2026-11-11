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
- [Standard Symfony host](#standard-symfony-host)
- [DDD / hexagonal host](#ddd--hexagonal-host)
  - [Panel access gate and actor resolver ports](#panel-access-gate-and-actor-resolver-ports)
- [Using it from a DDD / hexagonal application](#using-it-from-a-ddd--hexagonal-application)
  - [Keep the domain free of the bundle](#keep-the-domain-free-of-the-bundle)
  - [Adapter sketch](#adapter-sketch)
  - [Known limitations](#known-limitations)
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
php bin/console nowo:maintenance-mode:tick     # apply a scheduled window that started / ended (cron, every minute)
```

Scheduled windows (`schedule()`, panel "Schedule") work at read time without any job. Run `tick` from cron (`* * * * *
php bin/console nowo:maintenance-mode:tick`) or Symfony Scheduler when listeners must hear about them: it writes the
start / end of a window as an enable / disable (actor `schedule`, history context `trigger: schedule`) and so dispatches
`MaintenanceEnabledEvent` / `MaintenanceDisabledEvent`.

- The tick itself never changes whether maintenance is effectively on. A started window keeps its bounds, so the panel
  still shows it.
- Editing the schedule keeps working as without the tick: clearing or moving a window the tick started ends it at once
  (`MaintenanceDisabledEvent`, actor = the editor); extending its end or moving its start within the past keeps it on.
  **Disable** always ends it. The tick marks the state it switched on (`started_by_schedule: true`); a manual
  **Enable** or **Disable** clears the marker, so a schedule edit never ends a maintenance that was turned on by hand.
- A window that starts and ends between two ticks dispatches nothing. When no message was scheduled, the tick stores
  `default_message`.
- **Run the tick on one node only** (one cron entry, or a Scheduler transport consumed once). It re-reads the state
  before saving and skips when an operator changed it meanwhile, but two concurrent ticks can still record the same
  transition twice.

## Programmatic API

Type-hint `Nowo\MaintenanceModeBundle\Service\MaintenanceManagerInterface` (autowired to `MaintenanceManager`):

```php
use Nowo\MaintenanceModeBundle\Service\MaintenanceManagerInterface;

public function __construct(private MaintenanceManagerInterface $maintenance) {}

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
- `Nowo\MaintenanceModeBundle\Security\MaintenancePanelAccessGateInterface` (optional `security.access_gate`; the
  `Request`-typed `MaintenanceAccessGateInterface` is deprecated since 1.17 and still works until 2.0)
- `Nowo\MaintenanceModeBundle\Security\MaintenanceModeAccessCheckerInterface` (optional custom `access_checker`); the
  panel passes it the user of the security token (`null` when anonymous or without SecurityBundle)
- `Nowo\MaintenanceModeBundle\Security\MaintenanceActorResolverInterface` (optional `security.actor_resolver`; the
  `Request`-typed `ActorResolverInterface` is deprecated since 1.17): the actor stored as `updatedBy`, in history and in
  events for panel writes. Default `panel`; `SecurityTokenActorResolver` stores the logged-in user identifier

`MaintenanceStateStorageInterface::load()` contract: return a default `MaintenanceState` when there is no data yet and
throw only on a real failure. When it throws, the request listener logs the error (at most once a minute per process)
and applies `storage.on_error`:

- `fail_closed` (default): the maintenance page (503, `Retry-After`, `default_message`). No application code runs, as
  with the 1.15 500, and the unguarded Twig helpers are never reached on a non-excluded route.
- `fail_open`: the request is served as if maintenance were off. **It serves the live site while maintenance may be
  on** (for example migrations running while the state database is locked), so use it only when the state storage is
  independent of the application's database (a separate Redis, a file).
- `rethrow`: the exception goes through (1.15: a 500).

## Standard Symfony host

Zero config. Install the bundle, import its routes and you get: filesystem state and history, the default
`PasswordMaintenanceAccessGate` (optional `security.password_hash`, session flag, per-session and per-IP lockout), the
role checker driven by `security.access_roles`, and the static `panel` actor (or `SecurityTokenActorResolver`). The
gate and the actor resolver read the request through the bundle's own `MaintenanceAccessContext`, built at the HTTP
edge from the Symfony `Request` (client IP from `Request::getClientIp()`, so `framework.trusted_proxies` applies as
before) and the security token. Nothing changes for a standard host in 1.17; a custom gate or resolver written for
the 1.x `Request` interfaces keeps working (a deprecation is triggered) until 2.0.

## DDD / hexagonal host

Opt-in. The host implements bundle-owned, framework-free ports and points the configuration at them; no `Request`,
`Response`, `UserInterface` or Doctrine type crosses the boundary. The bundle has no Doctrine and no entity, so there
is no EntityManager or mapping to configure: storage is the two ports described in
[Custom storage](#custom-storage--access-gate). `tests/Integration/Kernel/DddHostAccessPortsKernelTest.php` boots a
kernel whose gate and actor resolver implement only the new ports; `HostPortsKernelTest.php` covers host storage.

### Panel access gate and actor resolver ports

| Port | Method(s) | Replaces (deprecated 1.17, removed 2.0) |
|---|---|---|
| `Security\MaintenancePanelAccessGateInterface` | `isPasswordRequired()`, `isGrantedFor(MaintenanceAccessContext)`, `isLockedOutFor(…)`, `authenticateFor(…, string $password)`, `revokeFor(…)` | `Security\MaintenanceAccessGateInterface` (`isGranted(Request)`, `authenticate(Request, string)`, `revoke(Request)`) |
| `Security\MaintenanceActorResolverInterface` | `resolveActorFor(MaintenanceAccessContext): ?string` | `Security\ActorResolverInterface` (`resolveActor(Request)`) |

`Security\MaintenanceAccessContext` (final, readonly) carries what a gate needs from one request:

| Field | Meaning |
|---|---|
| `path`, `method` | Path info and HTTP method |
| `clientIp` | Client IP resolved at the edge with the host's trusted proxies (`Request::getClientIp()`), or null |
| `headers` | Allow-listed headers only (`user-agent`, `accept-language`; lower-case names). Never cookies or credentials |
| `userIdentifier`, `roles` | The authenticated user from the security token (null / `[]` when anonymous) |
| `session` | `Security\MaintenanceAccessSessionInterface` (`get`, `set`, `remove`, `migrate`), or null when the request has no session |

Rules for a host gate:

- Deny by default: return false from `isGrantedFor()` when the context does not prove access (no session, unknown IP).
- Build nothing per user in service properties: the context is created per request and must not be stored
  (FrankenPHP worker mode with kernel reset false).
- When a service implements both the new and the deprecated interface, the panel calls the new one.
- Both `MaintenanceAccessGateInterface` and `MaintenancePanelAccessGateInterface` are aliased to the
  `security.access_gate` service (same for the two actor resolver interfaces); type-hint the one your service
  implements.

```php
namespace App\Infrastructure\Maintenance;

use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessContext;
use Nowo\MaintenanceModeBundle\Security\MaintenancePanelAccessGateInterface;

final readonly class OpsNetworkGate implements MaintenancePanelAccessGateInterface
{
    public function __construct(private OpsAccessPolicy $policy) {} // your application port

    public function isPasswordRequired(): bool { return true; }

    public function isGrantedFor(MaintenanceAccessContext $context): bool
    {
        return $context->userIdentifier !== null
            && $this->policy->mayOperateMaintenance($context->userIdentifier, $context->roles, $context->clientIp);
    }

    public function isLockedOutFor(MaintenanceAccessContext $context): bool { return false; }

    public function authenticateFor(MaintenanceAccessContext $context, string $password): bool { return false; }

    public function revokeFor(MaintenanceAccessContext $context): void {}
}
```

```yaml
nowo_maintenance_mode:
    security:
        access_gate: App\Infrastructure\Maintenance\OpsNetworkGate
        actor_resolver: Nowo\MaintenanceModeBundle\Security\SecurityTokenActorResolver # or your MaintenanceActorResolverInterface
```

## Using it from a DDD / hexagonal application

MaintenanceModeBundle is an **infrastructure / presentation** concern: a `kernel.request` listener that returns a 503
page, an optional admin panel, CLI commands and Twig helpers. It has **no Doctrine dependency and no host entities**:
its state (`Model\MaintenanceState`) and history (`Model\MaintenanceHistoryEntry`) are small value objects stored
behind two ports (filesystem JSON/JSONL by default). Your domain never needs to import a bundle class.

**Standard Symfony project (default):** nothing to wire; state and history go to the filesystem, the panel records the
static `panel` actor (or the user identifier with `SecurityTokenActorResolver`). **DDD / hexagonal project (opt-in):**
point the ports below at your own services; the bundle aliases each interface to the configured service id, so
type-hint the interfaces (never the final classes) and replace or decorate them through those aliases.
`tests/Integration/Kernel/HostPortsKernelTest.php` boots a kernel with host storage + actor resolver services.

### Keep the domain free of the bundle

- Define your own port in the application layer (e.g. `App\Application\Port\SiteAvailability`) and implement it in
  infrastructure with `Nowo\MaintenanceModeBundle\Service\MaintenanceManagerInterface` (`enable()`, `disable()`,
  `schedule()`, `clearSchedule()`, `applySchedule()`, `isEffectivelyEnabled()`, `getState()`, `history()`); double the
  interface in your infrastructure tests.
- React to changes with the events dispatched by `MaintenanceManager`, translating them into your own events:
  `Event\MaintenanceEnabledEvent` and `Event\MaintenanceDisabledEvent` (`$state`, `$actor`), and
  `Event\MaintenanceUpdatedEvent` (`$state`, `$action` = `schedule` | `clear_schedule` | `update`, `$actor`). Run
  `nowo:maintenance-mode:tick` so scheduled windows dispatch the enabled / disabled events too (actor `schedule`).
- Panel writes carry the actor from `Security\MaintenanceActorResolverInterface` (`security.actor_resolver`; set it to
  `Security\SecurityTokenActorResolver` to record the user identifier), and your access checker receives the token
  user.
- Store state where your infrastructure lives by implementing
  `Storage\MaintenanceStateStorageInterface` (`load(): MaintenanceState`, `save(MaintenanceState)`) and
  `Storage\MaintenanceHistoryStorageInterface` (`append(MaintenanceHistoryEntry)`, `list(int $limit = 50)`), then set
  `storage.state_storage` / `storage.history_storage`. `MaintenanceState::toArray()` / `fromArray()` give you a stable
  array shape to persist.
- Plug your authorisation in through `security.access_checker` (`Security\MaintenanceModeAccessCheckerInterface`) and
  `security.access_gate` (`Security\MaintenancePanelAccessGateInterface`, see [DDD / hexagonal host](#ddd--hexagonal-host)).
- Keep endpoints that must stay up (health checks, webhooks) out of the 503 with `exclusions.*`,
  `#[ExcludeFromMaintenance]` or the route default `ExcludeFromMaintenance::ROUTE_DEFAULT`.

### Adapter sketch

```php
namespace App\Infrastructure\Maintenance;

use App\Application\Port\SiteAvailability;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManagerInterface;

final class NowoSiteAvailability implements SiteAvailability
{
    public function __construct(private MaintenanceManagerInterface $maintenance) {}

    public function takeDown(string $reason, string $actorId): void
    {
        $this->maintenance->enable($reason, $actorId);
    }

    public function bringUp(string $actorId): void
    {
        $this->maintenance->disable($actorId);
    }

    public function isDown(): bool
    {
        return $this->maintenance->isEffectivelyEnabled();
    }
}
```

```php
namespace App\Infrastructure\Maintenance;

use App\Domain\Event\SiteWentDown;
use Nowo\MaintenanceModeBundle\Event\MaintenanceEnabledEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEventListener]
final class MaintenanceEventTranslator
{
    public function __construct(private MessageBusInterface $bus) {}

    public function __invoke(MaintenanceEnabledEvent $event): void
    {
        $this->bus->dispatch(new SiteWentDown($event->state->getMessage(), $event->actor));
    }
}
```

### Custom storage: transactions and concurrency

What the manager expects from a host `MaintenanceStateStorageInterface`:

- `save()` is durable when it returns: the manager dispatches `MaintenanceEnabledEvent` / `MaintenanceDisabledEvent` /
  `MaintenanceUpdatedEvent` right after `save()` (and the history `append()`) return. If your adapter writes inside a
  host transaction that commits later (e.g. a Doctrine adapter that only `persist()`s and leaves the flush to the
  caller), listeners run **before** your commit and may see a state that is then rolled back. Make the adapter commit
  on its own (own connection or an explicit transaction inside `save()`), or translate the events into your own
  messages dispatched after commit (Messenger `DispatchAfterCurrentBusStamp`, or a transactional outbox).
- `load()` returns the last saved state (read your own writes); returning a stale cached copy breaks `applySchedule()`'s
  compare-before-save check.
- `save()` replaces the whole state (load-modify-save, last write wins). The bundle does no optimistic locking; if
  several nodes can write at the same time (panel + CLI + tick), serialise the writes in the adapter (row lock, version
  column) or accept last-write-wins. Run `nowo:maintenance-mode:tick` on one node.
- The request listener calls `load()` on every main request: keep it cheap (cache in the adapter only with
  invalidation on `save()`, and nothing request- or user-specific in service properties: FrankenPHP worker mode).
- A throwing `load()` follows `storage.on_error` in the request listener only (see below).

### Known limitations

- Scheduled windows dispatch events only when `nowo:maintenance-mode:tick` runs (cron / Scheduler); a window that
  starts and ends between two ticks dispatches nothing.
- The Twig helpers (`nowo_maintenance_is_enabled()`, `nowo_maintenance_state()`), the panel, the preview and the CLI
  do not guard a throwing state storage; only the request listener applies `storage.on_error`. With the default
  `fail_closed` that leaves excluded routes and the panel (a visible 500 for the operator); with `fail_open`, a host
  layout that calls the helpers fails on served pages too.
- `tick` runs on one node; concurrent ticks can record a transition twice.
- State writes are load-modify-save with no optimistic locking (last write wins).

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
