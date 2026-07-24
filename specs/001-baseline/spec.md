# Maintenance Mode Bundle — Baseline product specification

**Package**: `nowo-tech/maintenance-mode-bundle`  
**Last audited**: 2026-07-24  
**Inventory**: [`code-inventory.md`](code-inventory.md)

## Overview

Maintenance Mode Bundle puts a Symfony application into **maintenance mode** (HTTP **503**) for visitors while allowing configurable **exclusions**, an optional **Twig admin panel**, **scheduled windows**, **append-only history**, and **pluggable storage / access gates**.

## Functional requirements

| ID | Requirement |
| --- | --- |
| FR-01 | When maintenance is effectively enabled, main requests receive **HTTP 503** with configurable `Retry-After`. |
| FR-02 | A Twig template (or HTML fallback) displays the maintenance message. |
| FR-03 | **Exclusions** by exact path, prefix, route name, glob, or regex skip the subscriber. |
| FR-04 | The panel path prefix is always excluded from the 503 subscriber. |
| FR-05 | **`MaintenanceManager`** enables, disables, updates, and reads state; appends history on each mutation. |
| FR-06 | Default **filesystem storage** persists state as JSON/YAML and history as JSONL. |
| FR-07 | Integrators may replace state/history storage via DI aliases. |
| FR-08 | The **Twig panel** supports enable, disable, schedule, history, login, and logout. |
| FR-09 | Optional **password gate** protects the panel; replaceable via `MaintenanceAccessGateInterface`. |
| FR-10 | **Scheduled enable/disable** windows affect `isEffectivelyEnabled()` without manual toggling. |
| FR-11 | Bundle Twig views register under namespace `NowoMaintenanceModeBundle`; app overrides win (REQ-TWIG-001). |
| FR-12 | Master config switch `nowo_maintenance_mode.enabled` disables the subscriber entirely. |

## User scenarios

### US-01 — Visitor during maintenance

**Given** maintenance is effectively enabled  
**When** a visitor requests a non-excluded route  
**Then** they receive HTTP 503 with the maintenance page.

### US-02 — Health check excluded

**Given** `/health` is in `exclusions.paths`  
**When** maintenance is enabled  
**Then** `/health` returns the normal application response.

### US-03 — Panel operator

**Given** the panel is enabled and the operator is authorized  
**When** they POST enable/disable/schedule  
**Then** state persists and history records the action.

### US-04 — Scheduled window

**Given** a scheduled enable/disable window covering “now”  
**When** `enabled` is false in storage  
**Then** visitors still receive 503 until the window ends.

### US-05 — Custom storage / gate

**Given** custom `state_storage`, `history_storage`, or `access_gate` service IDs  
**When** the container boots  
**Then** the bundle uses the integrator’s implementations.

## Out of scope

- Multi-tenant maintenance profiles.
- Shipping FrankenPHP as a runtime dependency.
- Mandatory Symfony SecurityBundle integration.

## Success criteria

| ID | Criterion |
| --- | --- |
| SC-01 | PHPUnit covers all PHP under `src/` (interfaces excluded where not executable). |
| SC-02 | PHPStan level 8 passes with `nowo-tech/phpstan-frankenphp` rulesets. |
| SC-03 | Demo FrankenPHP apps boot and smoke-test panel + 503 behaviour. |

## Validation

```bash
make qa
make phpstan
make test-coverage
```

## Traceability

- FR-01 … FR-04, FR-12: `tests/Unit/EventSubscriber/MaintenanceRequestSubscriberTest.php`
- FR-02: subscriber Twig/fallback tests
- FR-03: `tests/Unit/Exclusion/MaintenanceExclusionMatcherTest.php`
- FR-05: `tests/Unit/Service/MaintenanceManagerTest.php`
- FR-06 … FR-07: `tests/Unit/Storage/FilesystemStorageTest.php`, extension integration test
- FR-08: `tests/Unit/Controller/MaintenancePanelControllerTest.php`
- FR-09: `tests/Unit/Security/PasswordMaintenanceAccessGateTest.php`
- FR-10: `tests/Unit/Model/MaintenanceStateTest.php`
- FR-11: `tests/Unit/DependencyInjection/Compiler/TwigPathsPassTest.php`
