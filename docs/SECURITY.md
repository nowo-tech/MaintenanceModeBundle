# Security

## Attack surface

- Public 503 page (custom message content).
- Admin panel under a configurable prefix (default `/_maintenance`).
- Password gate session flag `_nowo_maintenance_mode_authorized`.
- State / history files under `var/maintenance/` (must not be web-accessible).

## Mitigations

- Store only a **password hash** in config (`password_hash` via env), never a plaintext password.
- Generate hashes with `php bin/console nowo:maintenance-mode:hash-password` (prefer interactive prompt).
- Set `security.password_protection: false` only when the panel is otherwise protected (VPN, IP firewall, Symfony security) — this disables the login section entirely.
- Do **not** advertise `/_maintenance` or login on the public 503 page; use `exclusions.*` for ops endpoints instead.
- Panel POST actions (enable / disable / schedule / clear-schedule / login / logout) require a **CSRF token** when `CsrfTokenManagerInterface` is available (FrameworkBundle default).
- Disable `password_protection` only when the panel is otherwise protected (VPN, IP firewall, Symfony security).
- Treat `security.bypass_token` as a **shared secret** (env var, rotate often). It is a soft QA escape hatch, not a substitute for panel auth or IP allowlists.
- Prefer `exclusions.ips` + trusted proxies over bypass tokens for standing operator access.
- Replace `MaintenanceAccessGateInterface` with your own voter/authenticator when integrating with SecurityBundle.
- Keep the panel prefix out of public links; treat it as an operational endpoint.

## Reporting

See `.github/SECURITY.md`.
