# Templates

Pages of MaintenanceModeBundle on the UiKit 2.1 page contract (REQ-UI-003). `MaintenancePanelController` builds
the UiKit view models with `Nowo\MaintenanceModeBundle\View\MaintenancePages`, and `Nowo\MaintenanceModeBundle\Panel\PanelView`
renders them with the shared page context. The 503 subscriber (`MaintenanceRequestSubscriber`) and the dev preview
(`MaintenancePreviewController`) render the public page. Templates extend a UiKit scaffold (or, for the public page,
the guest layout) and fill data blocks only. Contract test: `tests/Integration/Contract/PageContractTest.php`
(UiKit `PageContractTestCase`).

## Table of contents

- [Pages](#pages)
- [Shared page context](#shared-page-context)
- [Unique pages](#unique-pages)
- [Per-page overrides](#per-page-overrides)
- [Sample public pages (opt-in, not defaults)](#sample-public-pages-opt-in-not-defaults)
- [Bundle partials](#bundle-partials)
- [Allowed exceptions](#allowed-exceptions)

## Pages

| Page | Template | Scaffold | View model | Data blocks filled |
| --- | --- | --- | --- | --- |
| Panel (status, manual control, schedule) | `panel/index.html.twig` | `page/settings` | `SettingsPage` (header `panel.title` / `panel.intro`, header action Logout (POST + CSRF `FormView`, `data-testid="maintenance-logout"`) when the password gate is on, area nav Panel \| History; sections `status` (no form), `manual` (enable form, submit `panel.enable`), `schedule` (schedule form, submit `panel.save_schedule`); attributes `data-testid="maintenance-panel"`) + `section_actions` (`manual`: Disable, POST + CSRF `FormView`, variant danger; `schedule`: Clear schedule, POST + CSRF `FormView`), `status_items` (`list<DetailItem>`: message, activated, deactivated, scheduled enable / disable, updated by) | `nowo_ui_settings_section` (status: manual / effective badges + the `detail_list` partial; other sections: `parent()` + the section actions through the `action` partial in a `toolbar`) |
| History | `panel/history.html.twig` | `page/list` | `ListPage` (header `panel.history` / `panel.history.intro`, area nav; columns occurredAt, action, actor (priority 2), message (priority 3); rows = the last `web_ui.history_limit` `MaintenanceHistoryEntry` objects, newest first; `Pagination` in memory (`web_ui.history_per_page`, route `nowo_maintenance_mode_panel_history`, only when there is more than one page); `EmptyState` `panel.history.empty`; attributes `data-testid="maintenance-history"`) | `nowo_ui_list_row` (`<time>` cell, action in `badge_neutral`, muted `—` for empty actor / message) |
| Password login | `panel/login.html.twig` | `page/auth` (guest layout) | `AuthPage` (title `panel.login.heading`, intro `panel.login.intro`, password form, error `panel.login.error` / `panel.login.locked`, submit `panel.login.submit`; attributes `data-testid="maintenance-login"`) | — |

Every page passes `bundle: 'NowoMaintenanceModeBundle'` (theme exception `nowo_ui_kit.theme.bundles.NowoMaintenanceModeBundle`).
Admin pages pass `layout: web_ui.layout_template` (null = `nowo_ui_layout.admin`); the login page always uses
`nowo_ui_layout.guest`. Labels are `NowoMaintenanceModeBundle` translation keys (`PanelView::label()`). The page
headers carry the area navigation as `PageHeader::tabs` (`View\AreaNav` from `PanelView::nav()`: Panel \| History,
`aria-label` `panel.nav.aria_label`, `data-testid="maintenance-panel-tabs"`, items `maintenance-nav-panel|history`),
rendered by the UiKit `page_header` partial through the `tabs` partial (region `area-nav`). The same navigation is
published to the UiKit area-nav registry as area `maintenance_mode` (`nowo_ui_area_nav('maintenance_mode', {current: …})`;
`PanelView` implements `AreaNavProviderInterface`).

Section wrappers keep the 1.x test ids: `data-testid="maintenance-status|maintenance-manual|maintenance-schedule"`
(plus `maintenance-status-manual`, `maintenance-status-effective`, `maintenance-disable`, `maintenance-clear-schedule`).

## Shared page context

`PanelView::render()` adds to every panel page:

| Variable | Content |
| --- | --- |
| `path_prefix` | `web_ui.path_prefix` |
| `nowo_maintenance_mode_legacy_layout` | The 1.x layout wrapped by the legacy bridge, else null |

1.x variables kept for host templates set through `web_ui.templates.<page>`: panel `state`, `password_required`,
`flashes` (peeked, not consumed), `enable_form`, `disable_form`, `schedule_form`, `clear_schedule_form`,
`logout_form`; history `entries`; login `error`, `locked`, `login_form`.

Every panel page overrides one canonical layout block: `nowo_ui_flashes` (the `flashes` partial with
`trans_domain: NowoMaintenanceModeBundle`, so the bundle's flash keys are translated).

## Unique pages

- `maintenance/page.html.twig` — public maintenance page (503 and the dev preview `preview.path`). Extends
  `page.layout ?? nowo_ui_layout.guest` directly, sets `nowo_ui_bundle`, fills `nowo_ui_document_title`
  (`maintenance.page.title`), `nowo_ui_head` (`<meta name="robots" content="noindex">` + the 1.7
  `maintenance_head` / `maintenance_fonts` / `maintenance_styles` / `maintenance_head_extra` hooks) and
  `nowo_ui_content` (a `<section>` with the `auth_panel`, `card_body`, `badge_info`, `page_title`, `page_intro`,
  `muted` and `auth_footer` slots: blocks `maintenance_body`, `maintenance_brand`, `maintenance_heading`,
  `maintenance_message`, `maintenance_reassure`, `maintenance_footer`; `data-testid="maintenance-page"`).
  `nowo_ui_flashes` is empty (the 503 never reads or starts the session) and `nowo_ui_scripts` holds only
  `maintenance_scripts` (no UiKit script). No CDN, no inline style. Variables: `message`, `state`, `page`
  (`AuthPage` from `MaintenancePages::maintenance()`). It is not an admin scaffold page because the 503 must stay
  free of forms, session and JavaScript and keep the 1.7 block hooks.

## Per-page overrides

- Panel pages: `nowo_maintenance_mode.web_ui.templates.<page>` (`index`, `history`, `login`): a host template that
  extends `@NowoMaintenanceModeBundle/panel/<page>.html.twig` and overrides a data block (`nowo_ui_settings_section`,
  `nowo_ui_list_row`, `nowo_ui_list_after`, `nowo_ui_auth_fields`, `nowo_ui_auth_footer`, …). Prefer theming the
  UiKit slots (`nowo_ui_kit.theme`) and scaffolds once for every bundle.
- Public page: `nowo_maintenance_mode.templates.page`: a host template that extends
  `@NowoMaintenanceModeBundle/maintenance/page.html.twig` and overrides `maintenance_*` blocks, or that extends
  `@NowoUiKit/page/auth.html.twig` (the `page` variable is an `AuthPage`), or a standalone template (it receives
  `message` and `state`). Keep it free of database, session and security calls: it renders on `kernel.request`
  before the firewall. When it throws, the 503 falls back to a minimal built-in page (the preview route shows the
  error).

## Sample public pages (opt-in, not defaults)

`maintenance/examples/*.html.twig` (Bootstrap, Foundation, Tailwind and vanilla CSS designs) are **samples** for
`templates.page`: standalone documents that load their CSS framework and fonts **from CDNs** (jsDelivr, Google
Fonts). They are not used unless you point `templates.page` at one, are not part of the page contract and are not
covered by the contract test. Copy one into your app and serve its assets yourself for production. Catalogue:
[`MAINTENANCE-PAGE-EXAMPLES.md`](MAINTENANCE-PAGE-EXAMPLES.md).

## Bundle partials

| Partial | Role | Renders through |
| --- | --- | --- |
| `panel/_legacy_layout.html.twig` | Deprecated bridge around a 1.x `web_ui.layout_template` that renders `body` (adds `nowo-ui.css`) | Removed in 2.0 |

## Allowed exceptions

- (none) — the contract test declares no `allowedClasses()`.
