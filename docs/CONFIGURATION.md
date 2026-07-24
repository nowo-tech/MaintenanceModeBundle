# Configuration

Root alias: `nowo_maintenance_mode`.

| Key | Default | Description |
| --- | ------- | ----------- |
| `enabled` | `true` | Master switch for the 503 subscriber |
| `default_message` | English fallback | Message used when enabling without a custom one |
| `status_code` | `503` | HTTP status for the maintenance response |
| `retry_after` | `3600` | `Retry-After` header (seconds) |
| `subscriber_priority` | `32` | `kernel.request` subscriber priority (higher = earlier) |
| `panel.path_prefix` | `/_maintenance` | CRUD panel URL prefix (auto-excluded) |
| `panel.enabled` | `true` | Register panel controllers |
| `exclusions.paths` | `[]` | Exact paths |
| `exclusions.path_prefixes` | `[]` | Path prefixes |
| `exclusions.routes` | `[]` | Route names |
| `exclusions.patterns` | `[]` | Glob (`fnmatch`) or regex delimited with `#…#` / `~…~` |
| `security.password_protection` | `true` | Require password when a hash is set |
| `security.password_hash` | `null` | `password_hash()` (bcrypt/argon2id). Prefer env var |
| `security.access_gate` | `null` | Custom `MaintenanceAccessGateInterface` service |
| `storage.state_file` | `%kernel.project_dir%/var/maintenance/state.json` | State JSON/YAML |
| `storage.history_file` | `%kernel.project_dir%/var/maintenance/history.jsonl` | Append-only history |
| `storage.state_storage` / `history_storage` | `null` | Override storage services |
| `templates.*` | `@NowoMaintenanceModeBundle/...` | Overrideable Twig templates |

Profiles (`default_profile` / `profiles`) are **not** used: maintenance state is global (REQ-CFG-001 N/A).
