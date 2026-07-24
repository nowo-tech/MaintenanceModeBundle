# Code inventory — Maintenance Mode Bundle (`src/`)

**Baseline spec**: [`spec.md`](spec.md)  
**Package**: `nowo-tech/maintenance-mode-bundle`  
**Last audited**: 2026-07-24

100% inventory of production PHP under `src/`. Every file maps to at least one FR-* in the baseline product spec.

## Summary

| Category | Files |
| --- | ---: |
| Bundle entry | 1 |
| Command | 1 |
| Controller | 1 |
| DependencyInjection | 3 |
| EventSubscriber | 1 |
| Exclusion | 1 |
| Model | 2 |
| Security | 2 |
| Service | 1 |
| Storage | 4 |
| **Total production PHP (`src/*.php`)** | **17** |

Non-PHP production assets: `Resources/config/services.yaml`, `Resources/config/routes.yaml`, default + 6 example Twig maintenance views, 5 panel Twig views, 7 translation files.

## Bundle entry

| File | Responsibility | Spec |
| --- | --- | --- |
| `NowoMaintenanceModeBundle.php` | Registers extension and `TwigPathsPass` | FR-11 |

## Command

| File | Responsibility | Spec |
| --- | --- | --- |
| `Command/HashPasswordCommand.php` | CLI hash helper for panel `password_hash` | FR-09 |

## Controller

| File | Responsibility | Spec |
| --- | --- | --- |
| `Controller/MaintenancePanelController.php` | Panel CRUD, login/logout, CSRF | FR-08, FR-09 |

## DependencyInjection

| File | Responsibility | Spec |
| --- | --- | --- |
| `DependencyInjection/Configuration.php` | Config tree `nowo_maintenance_mode` | FR-01 … FR-12 |
| `DependencyInjection/MaintenanceModeExtension.php` | Wires storage, gate, manager, subscriber, panel | FR-05 … FR-09, FR-12 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig namespace + override paths | FR-11 |

## EventSubscriber

| File | Responsibility | Spec |
| --- | --- | --- |
| `EventSubscriber/MaintenanceRequestSubscriber.php` | 503 interceptor on `kernel.request` | FR-01 … FR-04, FR-12 |

## Exclusion

| File | Responsibility | Spec |
| --- | --- | --- |
| `Exclusion/MaintenanceExclusionMatcher.php` | Path/route/pattern exclusions | FR-03 |

## Model

| File | Responsibility | Spec |
| --- | --- | --- |
| `Model/MaintenanceState.php` | State snapshot + scheduling logic | FR-05, FR-10 |
| `Model/MaintenanceHistoryEntry.php` | Append-only history record | FR-05 |

## Security

| File | Responsibility | Spec |
| --- | --- | --- |
| `Security/MaintenanceAccessGateInterface.php` | Pluggable panel authorization contract | FR-09 |
| `Security/PasswordMaintenanceAccessGate.php` | Default password + session gate | FR-09 |

## Service

| File | Responsibility | Spec |
| --- | --- | --- |
| `Service/MaintenanceManager.php` | Enable/disable/update/history application service | FR-05 |

## Storage

| File | Responsibility | Spec |
| --- | --- | --- |
| `Storage/MaintenanceStateStorageInterface.php` | State persistence contract | FR-06, FR-07 |
| `Storage/MaintenanceHistoryStorageInterface.php` | History persistence contract | FR-06, FR-07 |
| `Storage/FilesystemMaintenanceStateStorage.php` | JSON/YAML filesystem state | FR-06 |
| `Storage/FilesystemMaintenanceHistoryStorage.php` | JSONL append-only history | FR-06 |

## Resources (non-PHP)

| Path | Responsibility | Spec |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service definitions | FR-05 … FR-09 |
| `Resources/config/routes.yaml` | Panel route import | FR-08 |
| `Resources/views/maintenance/page.html.twig` | Public 503 page | FR-02 |
| `Resources/views/panel/*.html.twig` | Admin panel templates | FR-08 |
| `Resources/translations/NowoMaintenanceModeBundle.*.yaml` | i18n (en, es, de, fr, it, nl, pt) | REQ-I18N-002 |

## Audit command

```bash
find src -type f -name '*.php' | wc -l
# Expected: 16
```
