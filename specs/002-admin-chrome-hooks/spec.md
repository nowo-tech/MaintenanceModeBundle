# Maintenance Mode Bundle — Admin chrome hooks

**Package**: `nowo-tech/maintenance-mode-bundle`  
**Target release**: 1.7.0 (minor, backwards compatible)  
**Status**: Implemented  
**Origin**: symfony-beacon spec 134 FR-010 (kit upstream improvements)  
**Baseline**: [`../001-baseline/spec.md`](../001-baseline/spec.md) (FR-20)

## Overview

Host applications forked whole panel pages (`panel/index`, `panel/history`, `panel/_nav`) only to repaint chrome: header, nav tabs, cards, table classes, empty state, test ids. This spec adds **Twig blocks**, a **CSS class map**, **configurable partial paths** and a few **flags**, so a host can go back to a `layout_template` bridge plus config (and, where it really wants a different structure, a small template that extends the bundle page and overrides one or two blocks).

Default output must stay **byte-identical** to 1.6.1, except the logout bug fix (FR-11), which only affects apps without an active password gate.

## Functional requirements

| ID | Requirement |
| --- | --- |
| FR-01 | `panel/index`, `panel/history` and `panel/login` wrap their existing markup in `maintenance_*` Twig blocks: nav, page header (title, intro, header actions), flashes, status, state details, manual / schedule / logout forms, toolbar, table wrapper, table, table head (+ head actions), table row (+ row actions), empty state, pagination, login intro / error / form. |
| FR-02 | `maintenance/page.html.twig` exposes blocks for `<html>` / `<body>` attributes, `title`, head (fonts, styles), head extra, body, brand, heading, message, reassure, footer and scripts. |
| FR-03 | `web_ui.classes` is a strict map of chrome CSS classes whose defaults equal the classes hard-coded before 1.7.0. Empty string / `null` drops the `class` attribute. Unknown keys fail configuration. |
| FR-04 | `web_ui.partials.nav`, `web_ui.partials.form_fields` and `web_ui.partials.pagination` (nullable) set the partials included by panel pages. The nav markup moves to a new `panel/_nav.html.twig`; an opt-in `panel/_nav_tabs.html.twig` uses UiKit `partials/_tabs` (honours `css_framework`, emits `aria-current`). |
| FR-05 | `web_ui.show_page_header` (default `true`) hides the bundle `<header><h1>` when the host layout prints the title. |
| FR-06 | `web_ui.document_title` (default `true`); when `false`, panel pages keep the host layout's `{% block title %}` via `parent()`. |
| FR-07 | `web_ui.test_ids` (default `false`) emits `data-testid` hooks: `maintenance-panel-tabs`, `maintenance-status`, `maintenance-manual`, `maintenance-schedule`, `maintenance-history`. |
| FR-08 | The resolved hooks are exposed as the Twig global `nowo_maintenance_mode_chrome` (`show_page_header`, `document_title`, `test_ids`, `classes`, `partials`); defaults live in `Twig\PanelChrome`. |
| FR-09 | The hard-coded nav `aria-label="Panel"` uses the new translation key `panel.nav.aria_label` (all 7 shipped locales; English value unchanged). |
| FR-11 | Fix: the logout form renders only when `password_required` is true (`password_required ?? true`); before, `|default(true)` treated `false` as empty and showed logout without a password gate. This is the only intentional default-output change. |
| FR-10 | Buttons keep `ui.btn(variant, null, web_ui.css_framework)` and flashes `ui.flash(type, web_ui.css_framework)` from UiKit; `classes.flash` appends extra classes. |

## User scenarios

### US-01 — Existing host upgrades

**Given** an app on 1.6.1 with no new keys  
**When** it upgrades to 1.7.0  
**Then** every panel page and the public page render byte-identical HTML (except: no logout button when no password gate is active, FR-11).

### US-02 — Host themes the history table by config

**Given** `web_ui.classes.table_*`, `show_page_header: false`, `test_ids: true`  
**When** an admin opens `/_maintenance/history`  
**Then** the table uses the host classes and test ids, with no page fork.

### US-03 — Host swaps the nav

**Given** `web_ui.partials.nav: 'admin/maintenance_nav.html.twig'`  
**When** a panel page renders  
**Then** the host partial is included with `active` and `path_prefix`.

### US-04 — Host restructures one section

**Given** `templates.panel_index` points to a host template that extends `@NowoMaintenanceModeBundle/panel/index.html.twig` and overrides `maintenance_status`  
**When** the panel renders  
**Then** only that section changes; forms, CSRF and routes stay bundle-owned.

## Out of scope

- Changing default markup beyond FR-11 (for example adding `aria-current` to the default nav); hosts can use `_nav_tabs.html.twig`.
- A countdown or brand assets on the default public page.
- Generic attribute maps on every element (only test ids are covered).

## Success criteria

| ID | Criterion |
| --- | --- |
| SC-01 | Render tests compare default output of index, history (with and without entries), login and the public page with golden files rendered from the 1.6.1 templates (byte-identical). |
| SC-02 | Render tests cover every class-map key family, each flag, each partial path and block overrides on all four templates. |
| SC-03 | Configuration and DI tests cover defaults, overrides, `null` → `''`, unknown-key rejection and the Twig extension argument. |
| SC-04 | PHPUnit lines coverage stays at 100%; PHPStan, CS Fixer, Rector, twig-cs-fixer and translation parity are green. |
| SC-05 | `docs/USAGE.md` ("Customising the admin chrome") includes a per-fork verdict for symfony-beacon. |
