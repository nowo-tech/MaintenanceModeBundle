# Security

## Attack surface

- Public 503 page (custom message content).
- Admin panel under a configurable prefix (default `/_maintenance`).
- Password gate session flag `_nowo_maintenance_mode_authorized`.
- State / history files under `var/maintenance/` (must not be web-accessible).

## Mitigations

- Store only a **password hash** in config (`password_hash` via env), never a plaintext password.
- Panel POST actions (enable / disable / schedule / login / logout) require a **CSRF token** when `CsrfTokenManagerInterface` is available (FrameworkBundle default).
- Disable `password_protection` only when the panel is otherwise protected (VPN, IP firewall, Symfony security).
- Replace `MaintenanceAccessGateInterface` with your own voter/authenticator when integrating with SecurityBundle.
- Keep the panel prefix out of public links; treat it as an operational endpoint.

## Reporting

See `.github/SECURITY.md`.
