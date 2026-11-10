# Maintenance Mode Bundle — Baseline product specification

**Package**: `nowo-tech/maintenance-mode-bundle`  
**Last audited**: 2026-11-01 (v1.15.0)
**Inventory**: [`code-inventory.md`](code-inventory.md)

## Overview

Maintenance Mode Bundle puts a Symfony application into **maintenance mode** (HTTP **503**) for visitors while allowing configurable **exclusions**, an optional **Twig admin panel**, **scheduled windows**, **append-only history**, and **pluggable storage / access gates**.

## Functional requirements

| ID | Requirement |
| --- | --- |
| FR-01 | When maintenance is effectively enabled, main requests receive **HTTP 503** with configurable `Retry-After`. |
| FR-02 | A Twig template (or HTML fallback) displays the maintenance message, with optional `page.illustration` (WebP first) and `page.countdown` to the scheduled end (nonced script; no JavaScript without it). |
| FR-03 | **Exclusions** by exact path, prefix, route name, glob, or regex skip the subscriber. |
| FR-04 | The panel path prefix is always excluded from the 503 subscriber. |
| FR-05 | **`MaintenanceManager`** enables, disables, updates, and reads state; appends history on each mutation. |
| FR-06 | Default **filesystem storage** persists state as JSON/YAML and history as JSONL. |
| FR-07 | Integrators may replace state/history storage via DI aliases. |
| FR-08 | The **Twig panel** supports enable, disable, schedule, history, login, and logout; every mutation is a Symfony Form with CSRF. Options `web_ui.show_preview_link` and `web_ui.form_theme`. |
| FR-09 | Optional **password gate** protects the panel; replaceable via `MaintenanceAccessGateInterface`. |
| FR-10 | **Scheduled enable/disable** windows affect `isEffectivelyEnabled()` without manual toggling. |
| FR-11 | Bundle Twig views register under namespace `NowoMaintenanceModeBundle`; app overrides win (REQ-TWIG-001). |
| FR-12 | Master config switch `nowo_maintenance_mode.enabled` disables the subscriber entirely. |
| FR-13 | CLI commands enable, disable, and report effective maintenance status for deployment and operations workflows. |
| FR-14 | The preview controller renders the configured public maintenance page in development without enabling maintenance mode. |
| FR-15 | State mutations dispatch enabled, disabled, or updated domain events for integrator side effects. |
| FR-16 | `#[ExcludeFromMaintenance]` and its route default exclude marked controllers or actions from maintenance mode. |
| FR-17 | Twig helpers expose maintenance state for application templates. |
| FR-18 | The bundle ships selectable example Twig maintenance themes. |
| FR-20 | Panel pages and the public page expose admin chrome hooks (Twig blocks, `web_ui.classes` map, `web_ui.partials` paths, header/title/test-id flags) with defaults that keep the rendered output unchanged — see [spec 002](../002-admin-chrome-hooks/spec.md). |
| FR-19 | Bundle HTTP services remain correct under FrankenPHP worker mode when the kernel is **not** reset between requests (`reset_kernel false`): no cross-request memoization of maintenance state, bypass tokens, or panel auth. |
| FR-21 | Panel, history, login and public pages render on the UiKit page contract (REQ-UI-003): `View\MaintenancePages` builds the view models, `Panel\PanelView` resolves per-page templates, the layout override and the Panel / History area nav; `panel/_legacy_layout.html.twig` bridges 1.x layouts (deprecated, removed in 2.0). The area tabs carry UiKit 2.17 `icon` names (ignored by older UiKit). |
| FR-22 | The container refuses to compile while the panel (`web_ui.enabled: true`) is registered without `security.csrf.token_manager`, `form.type_extension.csrf`, or with `framework.form.csrf_protection: false` (`CsrfProtectionPass`, fail closed — REQ-SEC-005). |
| FR-23 | Panel forms are FormKit Types (`#[FormKitConfig('maintenance_mode')]`); the extension prepends `nowo_form_kit.profiles.maintenance_mode` when FormKitBundle is registered and the host does not define it, with defaults that keep the rendered forms unchanged (REQ-TWIG-005 rule 11). |
| FR-24 | Panel and preview routes are opt-in: they exist only where the host imports `@NowoMaintenanceModeBundle/Resources/config/routes.yaml`; the controllers are not autoconfigured, so `routing.controllers` does not load them (REQ-SF-004 rule 7). |
| FR-25 | `Service\MaintenanceManagerInterface` is the application API (aliased to `MaintenanceManager`); the bundle's listener, controllers, commands and Twig extension depend on the interface. |
| FR-26 | `MaintenanceManagerInterface::applySchedule()` and `nowo:maintenance-mode:tick` materialise a scheduled window that started or ended (history + `MaintenanceEnabledEvent` / `MaintenanceDisabledEvent`, actor `schedule`) without changing the effective state. |
| FR-27 | Panel writes record the actor from `Security\ActorResolverInterface` (`security.actor_resolver`; default `panel`, opt-in `SecurityTokenActorResolver`); the access checker receives the token user (null when anonymous or without SecurityBundle). |
| FR-28 | A throwing state storage never turns every request into a 500: the request listener logs the error and applies `storage.on_error` (`fail_open` default, `fail_closed`, `rethrow`). |

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
| SC-04 | `code-inventory.md` maps 100% of production files under `src/` (67/67, audited 2026-11-01) to one or more `FR-*` IDs; enforced by `make check-spec-inventory` in `release-check`. |
| SC-05 | Worker scenario B regression tests pass (`WorkerModeNoKernelResetTest`). |

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
- FR-19: `tests/Unit/WorkerMode/WorkerModeNoKernelResetTest.php`; [docs/FRANKENPHP-WORKER-AUDIT.md](../../docs/FRANKENPHP-WORKER-AUDIT.md)
- FR-22: `tests/Unit/DependencyInjection/Compiler/CsrfProtectionPassTest.php`
- FR-23: `tests/Unit/Form/FormKitProfileTest.php`
- FR-24: `tests/Integration/Kernel/HostRoutingControllersKernelTest.php`
- FR-25: `tests/Integration/DependencyInjection/MaintenanceModeExtensionIntegrationTest.php`
- FR-26: `tests/Unit/Service/MaintenanceManagerApplyScheduleTest.php`, `tests/Unit/Command/TickCommandTest.php`
- FR-27: `tests/Unit/Security/ActorResolverTest.php`, `tests/Unit/Controller/MaintenancePanelControllerTest.php`
- FR-28: `tests/Unit/EventSubscriber/MaintenanceRequestSubscriberTest.php`
