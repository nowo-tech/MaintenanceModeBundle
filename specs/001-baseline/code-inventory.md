# Code inventory — Maintenance Mode Bundle (`src/`)

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/maintenance-mode-bundle`  
**Last audited**: 2026-11-01 (v1.15.0)

This is a 100% inventory of production files under `src/`: PHP, YAML configuration,
Twig views, and translations. Every file maps to one or more baseline `FR-*`
requirements.

## Summary

| Category | Files |
| --- | ---: |
| Bundle, dependency injection, and subscriber | 7 |
| Commands | 4 |
| Controllers | 2 |
| Events | 3 |
| Attribute and exclusion | 2 |
| Forms | 7 |
| Models, service, security, and storage | 18 |
| Twig extension, page views and chrome defaults | 4 |
| YAML configuration | 3 |
| Twig views | 17 |
| Translations | 7 |
| **Total production files under `src/`** | **79/79** |

## Bundle, dependency injection, and subscriber

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `NowoMaintenanceModeBundle.php` | Registers the bundle extension and the Twig paths, CSRF protection and access ports compiler passes. | FR-09, FR-11, FR-22 |
| `DependencyInjection/Configuration.php` | Defines the `nowo_maintenance_mode` configuration tree. | FR-01–FR-12, FR-15–FR-18 |
| `DependencyInjection/MaintenanceModeExtension.php` | Wires storage, manager, subscriber, controllers, commands, and Twig services; prepends the FormKit `maintenance_mode` profile. | FR-05–FR-09, FR-12–FR-18, FR-23 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Registers the bundle Twig namespace and application override paths. | FR-11 |
| `DependencyInjection/Compiler/AccessPortsPass.php` | Wires the panel to the access gate and actor resolver alias the host changed (new port first); fails the build when the two aliases of one port point to different host services (audit MK-S-M1/L4). | FR-09, FR-27 |
| `DependencyInjection/Compiler/CsrfProtectionPass.php` | Refuses to compile the container while the panel is enabled without the CSRF token manager or form CSRF protection (fail closed, REQ-SEC-005). | FR-08, FR-22 |
| `EventSubscriber/MaintenanceRequestSubscriber.php` | Intercepts applicable main requests and returns the maintenance response. | FR-01–FR-04, FR-12, FR-15 |

## Commands

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Command/EnableCommand.php` | Enables maintenance mode from the CLI, with an optional message and end time. | FR-13 |
| `Command/DisableCommand.php` | Disables maintenance mode from the CLI. | FR-13 |
| `Command/StatusCommand.php` | Reports effective maintenance status for deployment and operations scripts. | FR-13 |
| `Command/HashPasswordCommand.php` | Generates a password hash for panel protection. | FR-09 |
| `Command/TickCommand.php` | Materialises a scheduled window that started or ended (cron / Scheduler). | FR-13, FR-26 |

## Controllers

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Controller/MaintenancePanelController.php` | Provides panel status, mutations, scheduling, history, login/logout via Symfony Forms + CSRF. | FR-08, FR-09 |
| `Controller/MaintenancePreviewController.php` | Renders the configured public page for development preview without enabling maintenance. | FR-14 |

## Forms

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Form/AbstractMaintenanceFormType.php` | Shared CSRF / translation defaults for panel forms (empty block prefix); `#[FormKitConfig('maintenance_mode')]` + `FormOptionsTrait`. | FR-08, FR-23 |
| `Form/EnableMaintenanceType.php` | Enable form (message). | FR-08 |
| `Form/DisableMaintenanceType.php` | Disable form (CSRF only). | FR-08 |
| `Form/ScheduleMaintenanceType.php` | Schedule form (windows + message). | FR-08, FR-10 |
| `Form/ClearScheduleType.php` | Clear-schedule form (CSRF only). | FR-08, FR-10 |
| `Form/LoginMaintenanceType.php` | Panel password login form. | FR-08, FR-09 |
| `Form/LogoutMaintenanceType.php` | Panel logout form (CSRF only). | FR-08, FR-09 |

## Events

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Event/MaintenanceEnabledEvent.php` | Announces that maintenance was enabled. | FR-15 |
| `Event/MaintenanceDisabledEvent.php` | Announces that maintenance was disabled. | FR-15 |
| `Event/MaintenanceUpdatedEvent.php` | Announces a maintenance-state update. | FR-15 |

## Attribute and exclusion

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Attribute/ExcludeFromMaintenance.php` | Marks a controller or action as excluded from maintenance mode. | FR-16 |
| `Exclusion/MaintenanceExclusionMatcher.php` | Matches configured path, route, pattern, IP, token, and attribute exclusions. | FR-03, FR-16 |

## Models, service, security, and storage

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Model/MaintenanceState.php` | Represents state and evaluates scheduled maintenance windows. | FR-05, FR-10 |
| `Model/MaintenanceHistoryEntry.php` | Represents an append-only history entry. | FR-05 |
| `Service/MaintenanceManager.php` | Enables, disables, updates, reads state, records history, and dispatches events. | FR-05, FR-15 |
| `Service/MaintenanceManagerInterface.php` | Application API of the bundle (type-hinted inside the bundle and by hosts). | FR-05, FR-25, FR-26 |
| `Security/ActorResolverInterface.php` | Actor recorded for panel writes. | FR-27 |
| `Security/StaticActorResolver.php` | Default actor resolver (`panel`). | FR-27 |
| `Security/SecurityTokenActorResolver.php` | Opt-in actor resolver: the logged-in user identifier. | FR-27 |
| `Security/MaintenanceAccessGateInterface.php` | Defines the replaceable panel authorization contract. | FR-09 |
| `Security/MaintenancePanelAccessGateInterface.php` | Framework-free panel authorization port on `MaintenanceAccessContext` (REQ-ARCH-001 rule 3; replaces the deprecated `Request` gate). | FR-09 |
| `Security/MaintenanceActorResolverInterface.php` | Framework-free actor port on `MaintenanceAccessContext` (replaces the deprecated `Request` resolver). | FR-27 |
| `Security/MaintenanceAccessContext.php` | Per-request VO: path, method, client IP, allow-listed headers, user identifier, roles, session port; redacted in debug dumps. | FR-09, FR-27 |
| `Security/MaintenanceAccessSessionInterface.php` | Session port of the access context (`get`, `set`, `remove`, `migrate`). | FR-09 |
| `Security/Http/MaintenanceAccessContextFactory.php` | HTTP edge: builds the context from the `Request` (client IP via `getClientIp()`) and the security token. | FR-09, FR-27 |
| `Security/Http/SymfonyMaintenanceAccessSession.php` | Symfony session adapter of the session port; redacted in debug dumps. | FR-09 |
| `Security/PasswordMaintenanceAccessGate.php` | Implements the default password and session gate. | FR-09 |
| `Security/MaintenanceModeAccessCheckerInterface.php` | Role / custom access-checker contract for the panel (REQ-UI-002). | FR-08, FR-09 |
| `Security/ConfigurableMaintenanceModeAccessChecker.php` | Default role-based checker (`security.access_roles`, fail-closed when empty). | FR-08, FR-09 |
| `Security/AllowAllMaintenanceModeAccessChecker.php` | Demo-only checker used when `allow_unauthenticated` is true. | FR-08 |
| `Storage/MaintenanceStateStorageInterface.php` | Defines state persistence. | FR-06, FR-07 |
| `Storage/MaintenanceHistoryStorageInterface.php` | Defines history persistence. | FR-06, FR-07 |
| `Storage/FilesystemMaintenanceStateStorage.php` | Persists state to JSON/YAML on the filesystem. | FR-06 |
| `Storage/FilesystemMaintenanceHistoryStorage.php` | Appends history as JSONL on the filesystem. | FR-06 |

## Twig extension and chrome defaults

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Twig/MaintenanceExtension.php` | Exposes maintenance-state helpers and panel globals (incl. `nowo_maintenance_mode_chrome`) to Twig templates. | FR-17, FR-20 |
| `Twig/PanelChrome.php` | Defaults for the admin chrome class map, partial paths and flags ([spec 002](../002-admin-chrome-hooks/spec.md)). | FR-20 |
| `Panel/PanelView.php` | Renders the panel pages on the UiKit page contract (per-page template, layout override, Panel / History area nav with tab icons, legacy layout bridge). | FR-08, FR-21 |
| `View/MaintenancePages.php` | UiKit view models of the panel, history, login and public maintenance pages (plain data, worker safe). | FR-02, FR-08, FR-21 |

## YAML configuration

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Defines and autowires bundle services; controllers are not autoconfigured (explicit `controller.service_arguments`), so routes stay opt-in. | FR-05–FR-09, FR-13–FR-17, FR-24 |
| `Resources/config/routes.yaml` | Imports panel and preview routes (the only way they are registered). | FR-08, FR-14, FR-24 |
| `Resources/config/packages/nowo_maintenance_mode.yaml` | Provides the default bundle configuration. | FR-01–FR-12, FR-14–FR-17 |

## Twig views

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Resources/views/maintenance/page.html.twig` | Default public HTTP 503 page (`maintenance_*` blocks). | FR-02, FR-11, FR-20 |
| `Resources/views/maintenance/examples/bootstrap_calm.html.twig` | Bootstrap calm maintenance theme. | FR-11, FR-18 |
| `Resources/views/maintenance/examples/bootstrap_sunset.html.twig` | Bootstrap sunset maintenance theme. | FR-11, FR-18 |
| `Resources/views/maintenance/examples/foundation_garden.html.twig` | Foundation garden maintenance theme. | FR-11, FR-18 |
| `Resources/views/maintenance/examples/foundation_night.html.twig` | Foundation night maintenance theme. | FR-11, FR-18 |
| `Resources/views/maintenance/examples/tailwind_breeze.html.twig` | Tailwind breeze maintenance theme. | FR-11, FR-18 |
| `Resources/views/maintenance/examples/tailwind_aurora.html.twig` | Tailwind aurora maintenance theme. | FR-11, FR-18 |
| `Resources/views/maintenance/examples/idea_short.html.twig` | Minimal maintenance theme. | FR-11, FR-18 |
| `Resources/views/maintenance/examples/idea_compassion.html.twig` | Compassionate maintenance theme. | FR-11, FR-18 |
| `Resources/views/maintenance/examples/idea_playful.html.twig` | Playful maintenance theme. | FR-11, FR-18 |
| `Resources/views/maintenance/examples/idea_brand.html.twig` | Brand-oriented maintenance theme. | FR-11, FR-18 |
| `Resources/views/maintenance/examples/idea_countdown.html.twig` | Scheduled-end countdown maintenance theme. | FR-10, FR-11, FR-18 |
| `Resources/views/maintenance/examples/idea_updates.html.twig` | Status-update maintenance theme. | FR-11, FR-18 |
| `Resources/views/panel/index.html.twig` | Panel dashboard and mutation forms (`form_start` + unrendered `form_row` loop; chrome blocks / class map). | FR-08, FR-11, FR-20 |
| `Resources/views/panel/history.html.twig` | Panel history page (table / row / empty-state / pagination hooks). | FR-08, FR-11, FR-20 |
| `Resources/views/panel/login.html.twig` | Panel password-login page (chrome blocks / class map). | FR-08, FR-09, FR-11, FR-20 |
| `Resources/views/panel/_legacy_layout.html.twig` | Deprecated BC bridge for 1.x `web_ui.layout_template` layouts without `nowo_ui_content` (removed in 2.0). | FR-08, FR-21 |

## Translations

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Resources/translations/NowoMaintenanceModeBundle.en.yaml` | English bundle and example-page messages. | FR-02, FR-08, FR-18 |
| `Resources/translations/NowoMaintenanceModeBundle.es.yaml` | Spanish bundle and example-page messages. | FR-02, FR-08, FR-18 |
| `Resources/translations/NowoMaintenanceModeBundle.de.yaml` | German bundle and example-page messages. | FR-02, FR-08, FR-18 |
| `Resources/translations/NowoMaintenanceModeBundle.fr.yaml` | French bundle and example-page messages. | FR-02, FR-08, FR-18 |
| `Resources/translations/NowoMaintenanceModeBundle.it.yaml` | Italian bundle and example-page messages. | FR-02, FR-08, FR-18 |
| `Resources/translations/NowoMaintenanceModeBundle.nl.yaml` | Dutch bundle and example-page messages. | FR-02, FR-08, FR-18 |
| `Resources/translations/NowoMaintenanceModeBundle.pt.yaml` | Portuguese bundle and example-page messages. | FR-02, FR-08, FR-18 |

## Audit command

```bash
find src -type f | wc -l
# Expected: 79
```
