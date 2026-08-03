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

Profiles (`default_profile` / `profiles`) are **not** used: maintenance state is global (REQ-CFG-001 N/A).
