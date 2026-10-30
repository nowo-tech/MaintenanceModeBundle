# Configuration

Root alias: `nowo_maintenance_mode`.

| Key | Default | Description |
| --- | ------- | ----------- |
| `enabled` | `true` | Master switch for the 503 subscriber |
| `default_message` | Calm English fallback | Message used when enabling without a custom one |
| `status_code` | `503` | HTTP status for the maintenance response |
| `retry_after` | `3600` | Fallback `Retry-After` (seconds); overridden by remaining time until `scheduledDisableAt` when set |
| `subscriber_priority` | `31` | `kernel.request` listener priority (default after router so route/controller exclusions work) |
| `preview.enabled` | `null` (= `kernel.debug`) | Dev preview of the configured public page (like `/_error/503`) |
| `preview.path` | `/_maintenance_preview` | Preview URL (auto-excluded from 503) |
| `page.countdown` | `false` | *1.12*: live countdown to the scheduled end on the public page (a small script with the CSP nonce of `page.csp_nonce_attribute`); off = no JavaScript at all |
| `page.csp_nonce_attribute` | `csp_nonce` | *1.12*: request attribute holding the CSP nonce for the countdown script |
| `page.illustration.src` / `webp` / `alt` / `width` / `height` | `null` / `null` / `''` / `null` / `null` | *1.12*: image above the badge (asset path or URL; `webp` offered first; `alt` is a translation key or text) |
| `web_ui.show_preview_link` | `false` | *1.12*: link to the public page preview (`preview.path`) under the panel status |
| `web_ui.form_theme` | `null` | *1.12*: form theme of the panel section forms, e.g. `@NowoUiKit/form/nowo_ui_layout.html.twig` |
| `web_ui.enabled` | `true` | Register the admin panel (panel, history, password login). *Since 1.10* also replaces `panel.enabled` |
| `web_ui.path_prefix` | `/_maintenance` | Panel URL prefix (auto-excluded, segment-aware: `/_maintenance` and `/_maintenance/…`, not `/_maintenance-public`) |
| `web_ui.layout_template` | `null` | Layout the panel scaffolds extend instead of `nowo_ui_kit.layouts.admin` (null = UiKit layout). Must render the UiKit canonical blocks; a 1.x layout that renders `body` is wrapped in a deprecated bridge. The password login always uses `nowo_ui_kit.layouts.guest` |
| `web_ui.templates.<page>` | `{}` | Per-page template override: `index`, `history`, `login` (see [TEMPLATES.md](TEMPLATES.md)). Unknown pages are rejected |
| `web_ui.history_limit` | `100` | Most recent history entries listed |
| `web_ui.history_per_page` | `25` | History entries per page (UiKit pagination) |
| `templates.page` | `@NowoMaintenanceModeBundle/maintenance/page.html.twig` | Public 503 / preview page (UiKit guest layout + theme tokens; samples in [MAINTENANCE-PAGE-EXAMPLES.md](MAINTENANCE-PAGE-EXAMPLES.md)) |
| `security.access_roles` | `[ROLE_ADMIN]` | REQ-UI-002: at least one role grants panel access when `allow_unauthenticated` is false |
| `security.access_checker` | `null` | Optional service id implementing `MaintenanceModeAccessCheckerInterface` |
| `security.allow_unauthenticated` | `false` | DEV/DEMO only: skip Symfony role check (password gate may still apply). Requires SecurityBundle when `false` and panel is enabled |
| `security.password_protection` | `true` | When `true` and a hash is set, the panel shows login. Set to `false` to disable the login section entirely (trusted networks only). **Additional** to role check |
| `security.password_hash` | `null` | `password_hash()` (bcrypt/argon2id). Prefer env var; generate with `bin/console nowo:maintenance-mode:hash-password` |
| `security.login_max_attempts` | `5` | Failed panel password attempts allowed per session and per client IP (cache `cache.app`) within `login_lockout_seconds`; then the login answers 429 without checking the password. `0` = unlimited |
| `security.login_lockout_seconds` | `900` | Window (seconds) used to count failed panel password attempts |
| `security.bypass_token` | `null` | Shared secret for soft QA bypass (`?maintenance_bypass=TOKEN` + optional cookie). The cookie stores an HMAC derived from the token, never the token itself; rotating the token invalidates existing cookies |
| `security.bypass_query_parameter` | `maintenance_bypass` | Query parameter name for the soft bypass |
| `security.bypass_cookie_name` | `nowo_maintenance_bypass` | Cookie set after a successful query bypass |
| `security.bypass_set_cookie` | `true` | Persist bypass in an HttpOnly cookie after a successful query hit |
| `exclusions.paths` | `[]` | Exact paths that bypass 503 (never put panel-login links on the public page — use exclusions instead) |
| `exclusions.path_prefixes` | `[]` | Path prefixes that bypass 503. Segment-aware: `/api` matches `/api` and `/api/…`, not `/apix`. Append `*` (`/api*`) for a raw string prefix |
| `exclusions.routes` | `[]` | Symfony route names that bypass 503 |
| `exclusions.patterns` | `[]` | Glob (`fnmatch`) or regex delimited with `#…#` / `~…~` |
| `exclusions.ips` | `[]` | Client IPs or CIDR ranges (set `framework.trusted_proxies` behind a proxy) |
| `security.access_gate` | `null` | Custom `MaintenanceAccessGateInterface` service (ops password / session gate) |
| `storage.state_file` | `%kernel.project_dir%/var/maintenance/state.json` | State JSON/YAML |
| `storage.history_file` | `%kernel.project_dir%/var/maintenance/history.jsonl` | Append-only history |
| `storage.state_storage` / `history_storage` | `null` | Override storage services |

CSS framework, icon set, row-action display and every class / attribute / partial of the panel come from UiKit:
`nowo_ui_kit.css_framework`, `nowo_ui_kit.theme` (once for every bundle) and, for exceptions,
`nowo_ui_kit.theme.bundles.NowoMaintenanceModeBundle`. See [TEMPLATES.md](TEMPLATES.md).

### Deprecated keys (1.10, removed in 2.0)

| Deprecated key | Use instead |
| --- | --- |
| `panel.enabled` | `web_ui.enabled` |
| `panel.path_prefix` | `web_ui.path_prefix` |
| `templates.panel_layout` | `web_ui.layout_template` (the 1.x default `@NowoMaintenanceModeBundle/panel/layout.html.twig` maps to `null`) |
| `templates.panel_index` / `panel_history` / `panel_login` | `web_ui.templates.index` / `history` / `login` |
| `web_ui.css_framework` / `web_ui.icon_set` | `nowo_ui_kit.css_framework` / `nowo_ui_kit.icon_set` (seeded from the old keys when the host does not set them) |
| `web_ui.classes.*` | `nowo_ui_kit.theme.bundles.NowoMaintenanceModeBundle.classes` (bridged automatically, see [UPGRADING](UPGRADING.md#template-contract-110)) |
| `web_ui.partials.*` | ignored — `nowo_ui_kit.theme.partials` |
| `web_ui.show_page_header` / `document_title` / `test_ids` | no effect — UiKit page header, scaffold titles, `data-testid` always rendered |

The canonical key wins when both are set. Twig globals `nowo_maintenance_mode_layout_template`,
`nowo_maintenance_mode_css_framework`, `nowo_maintenance_mode_icon_set` and `nowo_maintenance_mode_chrome` are
deprecated (kept for 1.x host templates, not read by the bundle pages).

Profiles (`default_profile` / `profiles`) are **not** used: maintenance state is global (REQ-CFG-001 N/A).

## FormKit profile (REQ-TWIG-005)

The panel forms are Symfony Form Types built with FormKit (`#[FormKitConfig('maintenance_mode')]`), rendered with
`form_start` / the `{% for child in form %}{% if not child.rendered %}{{ form_row(child) }}{% endif %}{% endfor %}` loop
/ `form_end` — never raw `<form>` / `<input>` (keep it that way in overrides). Their field options come from the FormKit
profile `nowo_form_kit.profiles.maintenance_mode`, which the bundle prepends when FormKitBundle is registered:

```yaml
nowo_form_kit:
    profiles:
        maintenance_mode:
            alias: maintenance_mode
            translation_domain: NowoMaintenanceModeBundle
            auto_placeholder: false   # the forms set their labels explicitly
            auto_help: false
```

Define `profiles.maintenance_mode` in your own `nowo_form_kit` config to theme the forms (for example
`defaults: { attr: { class: 'form-control' } }` or `field_types`); the bundle then does not prepend its default. The
stock profile adds no classes: the host form theme and the UiKit scaffolds style the rows.
