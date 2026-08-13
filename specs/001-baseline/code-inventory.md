# Code inventory — Maintenance Mode Bundle (`src/`)

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/maintenance-mode-bundle`  
**Last audited**: 2026-07-26

This is a 100% inventory of production files under `src/`: PHP, YAML configuration,
Twig views, and translations. Every file maps to one or more baseline `FR-*`
requirements.

## Summary

| Category | Files |
| --- | ---: |
| Bundle, dependency injection, and subscriber | 5 |
| Commands | 4 |
| Controllers | 2 |
| Events | 3 |
| Attribute and exclusion | 2 |
| Models, service, security, and storage | 9 |
| Twig extension | 1 |
| YAML configuration | 3 |
| Twig views | 17 |
| Translations | 7 |
| **Total production files under `src/`** | **53/53** |

## Bundle, dependency injection, and subscriber

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `NowoMaintenanceModeBundle.php` | Registers the bundle extension and Twig compiler pass. | FR-11 |
| `DependencyInjection/Configuration.php` | Defines the `nowo_maintenance_mode` configuration tree. | FR-01–FR-12, FR-15–FR-18 |
| `DependencyInjection/MaintenanceModeExtension.php` | Wires storage, manager, subscriber, controllers, commands, and Twig services. | FR-05–FR-09, FR-12–FR-18 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Registers the bundle Twig namespace and application override paths. | FR-11 |
| `EventSubscriber/MaintenanceRequestSubscriber.php` | Intercepts applicable main requests and returns the maintenance response. | FR-01–FR-04, FR-12, FR-15 |

## Commands

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Command/EnableCommand.php` | Enables maintenance mode from the CLI, with an optional message and end time. | FR-13 |
| `Command/DisableCommand.php` | Disables maintenance mode from the CLI. | FR-13 |
| `Command/StatusCommand.php` | Reports effective maintenance status for deployment and operations scripts. | FR-13 |
| `Command/HashPasswordCommand.php` | Generates a password hash for panel protection. | FR-09 |

## Controllers

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Controller/MaintenancePanelController.php` | Provides panel status, mutations, scheduling, history, login/logout via Symfony Forms + CSRF. | FR-08, FR-09 |
| `Controller/MaintenancePreviewController.php` | Renders the configured public page for development preview without enabling maintenance. | FR-14 |

## Forms

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Form/AbstractMaintenanceFormType.php` | Shared CSRF / translation defaults for panel forms (empty block prefix). | FR-08 |
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
| `Security/MaintenanceAccessGateInterface.php` | Defines the replaceable panel authorization contract. | FR-09 |
| `Security/PasswordMaintenanceAccessGate.php` | Implements the default password and session gate. | FR-09 |
| `Storage/MaintenanceStateStorageInterface.php` | Defines state persistence. | FR-06, FR-07 |
| `Storage/MaintenanceHistoryStorageInterface.php` | Defines history persistence. | FR-06, FR-07 |
| `Storage/FilesystemMaintenanceStateStorage.php` | Persists state to JSON/YAML on the filesystem. | FR-06 |
| `Storage/FilesystemMaintenanceHistoryStorage.php` | Appends history as JSONL on the filesystem. | FR-06 |

## Twig extension

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Twig/MaintenanceExtension.php` | Exposes maintenance-state helpers to Twig templates. | FR-17 |

## YAML configuration

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Defines and autowires bundle services. | FR-05–FR-09, FR-13–FR-17 |
| `Resources/config/routes.yaml` | Imports panel and preview routes. | FR-08, FR-14 |
| `Resources/config/packages/nowo_maintenance_mode.yaml` | Provides the default bundle configuration. | FR-01–FR-12, FR-14–FR-17 |

## Twig views

| Path | Responsibility | Spec FR-* IDs |
| --- | --- | --- |
| `Resources/views/maintenance/page.html.twig` | Default public HTTP 503 page. | FR-02, FR-11 |
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
| `Resources/views/panel/layout.html.twig` | Shared panel layout. | FR-08, FR-11 |
| `Resources/views/panel/index.html.twig` | Panel dashboard and mutation forms (`form_start` + unrendered `form_row` loop). | FR-08, FR-11 |
| `Resources/views/panel/_form_fields.html.twig` | Shared unrendered-field `form_row` loop for panel forms. | FR-08, FR-11 |
| `Resources/views/panel/history.html.twig` | Panel history page. | FR-08, FR-11 |
| `Resources/views/panel/login.html.twig` | Panel password-login page. | FR-08, FR-09, FR-11 |

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
# Expected: 53
```
