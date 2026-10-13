# Configuration

Root alias: `nowo_maintenance_mode`.

| Key | Default | Description |
| --- | ------- | ----------- |
| `enabled` | `true` | Master switch for the 503 subscriber |
| `default_message` | Calm English fallback | Message used when enabling without a custom one |
| `templates.*` | `@NowoMaintenanceModeBundle/...` | Overrideable Twig templates (see [MAINTENANCE-PAGE-EXAMPLES.md](MAINTENANCE-PAGE-EXAMPLES.md) for framework examples) |
| `status_code` | `503` | HTTP status for the maintenance response |
| `retry_after` | `3600` | Fallback `Retry-After` (seconds); overridden by remaining time until `scheduledDisableAt` when set |
| `subscriber_priority` | `31` | `kernel.request` listener priority (default after router so route/controller exclusions work) |
| `preview.enabled` | `null` (= `kernel.debug`) | Dev preview of the configured public page (like `/_error/503`) |
| `preview.path` | `/_maintenance_preview` | Preview URL (auto-excluded from 503) |
| `panel.path_prefix` | `/_maintenance` | CRUD panel URL prefix (auto-excluded) |
| `panel.enabled` | `true` | Register panel controllers |
| `web_ui.enabled` | `true` | Look-and-feel contract active (REQ-UI-001) |
| `web_ui.layout_template` | `@NowoMaintenanceModeBundle/panel/layout.html.twig` | Outer Twig layout panel pages extend (set to the **project** layout in host apps). Syncs `templates.panel_layout` |
| `web_ui.css_framework` | `custom` | Host CSS stack: `bootstrap5`, `tailwind`, `foundation`, `custom`, … |
| `web_ui.icon_set` | `none` | Icon strategy (`bootstrap-icons`, `tabler-icons`, `ux_icon`, `svg_inline`, `none`) |
| `web_ui.show_page_header` | `true` | *(1.7.0)* Render the bundle `<header><h1>` on panel pages. Set `false` when the host layout already prints the page title |
| `web_ui.document_title` | `true` | *(1.7.0)* Panel pages fill the layout `{% block title %}`. Set `false` to keep the host layout's title (`parent()`) |
| `web_ui.test_ids` | `false` | *(1.7.0)* Emit `data-testid` hooks: `maintenance-panel-tabs`, `maintenance-status`, `maintenance-manual`, `maintenance-schedule`, `maintenance-history` |
| `web_ui.classes.*` | see below | *(1.7.0)* Admin chrome CSS class map. Empty string / `null` = no `class` attribute. Unknown keys are rejected |
| `web_ui.partials.nav` | `@NowoMaintenanceModeBundle/panel/_nav.html.twig` | *(1.7.0)* Panel / History navigation partial (receives `active`, `path_prefix`). Shipped alternative: `@NowoMaintenanceModeBundle/panel/_nav_tabs.html.twig` (UiKit tabs, `aria-current`) |
| `web_ui.partials.form_fields` | `@NowoMaintenanceModeBundle/panel/_form_fields.html.twig` | *(1.7.0)* Loop that renders every not-yet-rendered form child (receives `form`) |
| `web_ui.partials.pagination` | `null` | *(1.7.0)* Optional partial included after the history table (receives `entries`, `path_prefix`) |
| `security.access_roles` | `[ROLE_ADMIN]` | REQ-UI-002: at least one role grants panel access when `allow_unauthenticated` is false |
| `security.access_checker` | `null` | Optional service id implementing `MaintenanceModeAccessCheckerInterface` |
| `security.allow_unauthenticated` | `false` | DEV/DEMO only: skip Symfony role check (password gate may still apply). Requires SecurityBundle when `false` and panel is enabled |
| `security.password_protection` | `true` | When `true` and a hash is set, the panel shows login. Set to `false` to disable the login section entirely (trusted networks only). **Additional** to role check |
| `security.password_hash` | `null` | `password_hash()` (bcrypt/argon2id). Prefer env var; generate with `bin/console nowo:maintenance-mode:hash-password` |
| `security.bypass_token` | `null` | Shared secret for soft QA bypass (`?maintenance_bypass=TOKEN` + optional cookie) |
| `security.bypass_query_parameter` | `maintenance_bypass` | Query parameter name for the soft bypass |
| `security.bypass_cookie_name` | `nowo_maintenance_bypass` | Cookie set after a successful query bypass |
| `security.bypass_set_cookie` | `true` | Persist bypass in an HttpOnly cookie after a successful query hit |
| `exclusions.paths` | `[]` | Exact paths that bypass 503 (never put panel-login links on the public page — use exclusions instead) |
| `exclusions.path_prefixes` | `[]` | Path prefixes that bypass 503 |
| `exclusions.routes` | `[]` | Symfony route names that bypass 503 |
| `exclusions.patterns` | `[]` | Glob (`fnmatch`) or regex delimited with `#…#` / `~…~` |
| `exclusions.ips` | `[]` | Client IPs or CIDR ranges (set `framework.trusted_proxies` behind a proxy) |
| `security.access_gate` | `null` | Custom `MaintenanceAccessGateInterface` service (ops password / session gate) |
| `storage.state_file` | `%kernel.project_dir%/var/maintenance/state.json` | State JSON/YAML |
| `storage.history_file` | `%kernel.project_dir%/var/maintenance/history.jsonl` | Append-only history |
| `storage.state_storage` / `history_storage` | `null` | Override storage services |

### `web_ui.classes` defaults (1.7.0)

Defaults equal the classes hard-coded before 1.7.0, so the rendered panel is unchanged until you set a key.

| Key | Default | Applied to |
| --- | --- | --- |
| `nav` | `nowo-ui-nav` | `<nav>` in `panel/_nav.html.twig` |
| `nav_link` | `''` | Each nav `<a>` |
| `page_header` | `nowo-ui-page-header` | `<header>` |
| `page_title` | `''` | `<h1>` |
| `page_intro` | `''` | Login intro `<p>` |
| `flash` | `''` | Appended to `ui.flash(type)` |
| `status_line` | `''` | Index status `<p>` |
| `status_on` | `nowo-ui-status nowo-ui-status--on status on` | ON / ENABLED badge |
| `status_off` | `nowo-ui-status nowo-ui-status--off status off` | off / disabled badge |
| `card` | `nowo-ui-card card` | Index / login cards |
| `section_title` | `''` | Schedule `<h2>` |
| `form` | `''` | `class` on every panel `<form>` |
| `form_actions` | `''` | When set, wraps each submit button in `<div class="…">` |
| `table_wrap` | `nowo-ui-card nowo-ui-table-wrap card` | History table wrapper |
| `table` | `nowo-ui-table` | History `<table>` |
| `table_head_row` / `table_head_cell` | `''` | `<thead><tr>` / `<th>` |
| `table_row` / `table_cell` | `''` | Body `<tr>` / `<td>` |
| `table_cell_muted` | `''` | Extra class on the message `<td>` |
| `empty` | `nowo-ui-empty` | Empty-state `<td>` |
| `login_error` | `nowo-ui-error error` | Login error `<p>` |

Buttons keep using `ui.btn(variant, null, web_ui.css_framework)` from UiKit. Twig global: `nowo_maintenance_mode_chrome` (`show_page_header`, `document_title`, `test_ids`, `classes`, `partials`). See [USAGE — Customising the admin chrome](USAGE.md#customising-the-admin-chrome).

Profiles (`default_profile` / `profiles`) are **not** used: maintenance state is global (REQ-CFG-001 N/A).
