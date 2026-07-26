# Security

## Table of contents

- [Scope](#scope)
- [Attack surface](#attack-surface)
- [Threat model](#threat-model)
- [Mitigations](#mitigations)
- [Secrets and cryptography](#secrets-and-cryptography)
- [Logging](#logging)
- [Dependencies and updates](#dependencies-and-updates)
- [Permissions and exposure](#permissions-and-exposure)
- [Reporting a vulnerability](#reporting-a-vulnerability)
- [Release security checklist (12.4.1)](#release-security-checklist-1241)

## Scope

This document covers security considerations for **nowo-tech/maintenance-mode-bundle** — a Symfony bundle that returns HTTP **503** during maintenance, serves a public maintenance page, and optionally exposes a Twig admin panel with password gate, scheduling, and history.

**In scope:** kernel request interception, exclusions, panel routes, password/session gate, bypass token, filesystem state/history under `var/maintenance/`, Twig templates, CLI helpers, and Flex recipe defaults.

**Out of scope:** Host application authentication (SecurityBundle firewalls beyond the bundle gate), reverse-proxy / WAF configuration, and OS-level protection of `var/`.

The bundle does **not** spawn subprocesses or perform third-party HTTP calls (REQ-RUNTIME-001 N/A).

## Attack surface

| Input / surface | Description |
| --- | --- |
| **Public HTTP** | Any non-excluded request while maintenance is effectively enabled (503 + maintenance Twig/HTML) |
| **Admin panel** | Configurable prefix (default `/_maintenance`) — enable/disable/schedule/history/login/logout |
| **Preview route** | `/_maintenance_preview` (dev-oriented page preview) |
| **Configuration** | YAML under `nowo_maintenance_mode`, env-backed `password_hash` / `bypass_token` |
| **Session** | Flag `_nowo_maintenance_mode_authorized` after successful panel login |
| **Filesystem** | State JSON/YAML and history JSONL under configurable `var/maintenance/` |
| **CLI** | `nowo:maintenance-mode:*` (enable/disable/status/hash-password) |
| **Twig / translations** | Public 503 page and panel templates |

## Threat model

| Category | Risk | Applicability |
| --- | --- | --- |
| **Authz / privilege escalation** | Unauthenticated access to panel mutations | Panel when `password_protection` is misconfigured or disabled without compensating controls |
| **Credential theft** | Stolen password hash or bypass token from config/VCS | `password_hash`, `security.bypass_token` |
| **CSRF** | Forged panel POST (enable/disable/schedule/login) | Panel forms |
| **XSS** | Stored/custom HTML in maintenance message rendered unsafely | Public page / custom templates |
| **Session fixation / hijack** | Session flag treated as sole auth | Password gate |
| **Information disclosure** | Panel URL or bypass token leaked on public 503 page | Public templates |
| **Path traversal / file write** | Malicious storage paths | Integrator-configured storage directories |
| **DoS** | High volume against public 503 or panel login | Lightweight handlers; rate limiting is the host's responsibility |
| **SSRF / subprocess** | Outbound HTTP or process spawn | Not applicable |

## Mitigations

| Threat | Control |
| --- | --- |
| **Panel auth** | Default password gate with Symfony password hasher; session flag only after successful verify |
| **Plaintext passwords** | Store only `password_hash` (env); generate with `nowo:maintenance-mode:hash-password` |
| **CSRF** | Panel POSTs require CSRF when `CsrfTokenManagerInterface` is available (FrameworkBundle default) |
| **Bypass token** | Soft QA escape hatch via shared secret; rotate often; prefer IP allowlists + trusted proxies |
| **Public page leakage** | Do not advertise panel URL or login on the public 503 page; use `exclusions.*` for ops endpoints |
| **XSS** | Prefer Twig auto-escaping; treat custom maintenance HTML as **trusted admin content** |
| **Storage exposure** | `var/maintenance/` must not be web-accessible; document in host runbooks |
| **Disable password gate** | `security.password_protection: false` only when panel is otherwise protected (VPN, IP firewall, SecurityBundle) |
| **Pluggable gate** | Replace `MaintenanceAccessGateInterface` with a SecurityBundle voter/authenticator when needed |

## Secrets and cryptography

- Never commit real production password hashes, bypass tokens, or `.env` secrets.
- Password hashing uses Symfony hasher / `password_hash` — no custom crypto.
- Demo/recipe configs use **placeholders** only.
- `security.bypass_token` is a shared secret (env var); not a substitute for panel auth.

## Logging

| Data | Behavior |
| --- | --- |
| **History JSONL** | Records enable/disable/schedule actions (operator metadata as configured) — avoid storing secrets |
| **Symfony logger** | Must never log bypass tokens, password hashes, or raw session auth flags |
| **Public 503** | Prefer cacheable/static responses where possible |

## Dependencies and updates

- Run `composer audit` before each release and triage findings.
- Dependabot covers Composer and GitHub Actions (see `.github/dependabot.yml`).
- Apply Symfony security fixes in consuming applications promptly.
- Published package excludes `/demo` and `/.cursor` via `archive.exclude`.

## Permissions and exposure

| Endpoint / feature | Exposure | Recommendation |
| --- | --- | --- |
| Public 503 page | Public during maintenance | Expected; keep copy free of secrets/panel URLs |
| Panel (`/_maintenance`) | Ops-only | Firewall / VPN / SecurityBundle; keep password protection on unless compensated |
| Bypass token query/header | Soft escape | Rotate; prefer IP exclusions |
| CLI commands | Host shell | Restrict to deploy operators |
| Demo app | Local only | Do not deploy demo routes unchanged to production |

## Reporting a vulnerability

Report security issues **privately**:

1. Do **not** open a public GitHub issue for security-sensitive bugs.
2. Use [GitHub Security Advisories](https://github.com/nowo-tech/MaintenanceModeBundle/security/advisories) or follow [`.github/SECURITY.md`](../.github/SECURITY.md).
3. Include steps to reproduce, affected versions, and impact.

## Release security checklist (12.4.1)

Before tagging a release, confirm:

| Item | Notes |
|------|-------|
| **`docs/SECURITY.md`** | This document is current and matches bundle behavior. |
| **`.github/SECURITY.md`** | Public policy present and product name is correct. |
| **No committed secrets** | No real password hashes for production, bypass tokens, or `.env` secrets in tracked files. |
| **Recipe / demo config** | Demo uses placeholders; panel password hash is demo-only. |
| **Input / output** | Maintenance message / HTML treated as trusted admin content; CSRF on panel POSTs. |
| **Dependencies** | `composer audit` run and findings triaged. |
| **Logging** | Do not log bypass tokens, password hashes, or session auth flags. |
| **Cryptography** | Password hashing via Symfony hasher / `password_hash`; no custom crypto. |
| **Permissions / exposure** | Host must firewall `/_maintenance`; `var/maintenance/` not web-accessible. |
| **Limits / DoS** | Public 503 path is cacheable/static where possible; panel is ops-only. |
| **Release notes** | Security-relevant changes reflected in `CHANGELOG.md` / `UPGRADING.md` when needed. |

Recommended commands:

```bash
composer audit
make release-check
```
