# Spec-driven development

## Layers in sync

1. **Product behaviour** — what consuming apps may rely on (503 maintenance, exclusions, panel, pluggable storage/gate). Documented in [USAGE.md](USAGE.md) and [CONFIGURATION.md](CONFIGURATION.md).
2. **Traceability anchors** — `REQ-*` identifiers in Makefiles / demos (e.g. REQ-CS-005, REQ-DEMO-010, REQ-MAKE-001).
3. **GitHub Spec Kit baseline** — when present under `specs/001-baseline/`; see [SPEC-KIT.md](SPEC-KIT.md).

## User stories

| ID | Intent | Docs |
| --- | --- | --- |
| US-01 | Put the site in maintenance (503) for visitors | USAGE, CONFIGURATION |
| US-02 | Exclude health/API/panel paths | CONFIGURATION |
| US-03 | Enable/disable via Twig panel with optional password | USAGE, SECURITY |
| US-04 | Schedule enable/disable windows | USAGE |
| US-05 | Keep an append-only history | USAGE |
| US-06 | Replace filesystem storage or access gate | USAGE |

## Functional scope

**In scope:** kernel.request subscriber, exclusions, filesystem defaults, password gate, Twig panel CRUD, overrideable templates/translations.

**Non-goals:** multi-tenant profiles (REQ-CFG-001 N/A — global state), coupling to a single SecurityBundle setup, shipping FrankenPHP as a runtime dependency.

## Validating the functional spec

```bash
make qa
make phpstan
make test
```

Behaviour changes require tests under `tests/`.

## Requirement identifiers (selected)

| ID | Meaning | Location |
| --- | --- | --- |
| REQ-CS-005 | phpstan-frankenphp require-dev + rulesets | `composer.json`, `phpstan.neon.dist` |
| REQ-MAKE-001 | Root Makefile + ensure-up | `Makefile` |
| REQ-TWIG-001/002 | Overrides + `NowoMaintenanceModeBundle` NS | `TwigPathsPass`, views |
| REQ-I18N-002/003 | en+es + domain | `Resources/translations/` |
| REQ-DEMO-002/010 | FrankenPHP demos + worker default | `demo/`, `docs/DEMO-FRANKENPHP.md` |

## Contributor workflow

Clarify → implement with tests → update docs/config → `make release-check`.

## Relationship to Engram

See [ENGRAM.md](ENGRAM.md) for AI memory; this file owns product behaviour and REQ traceability.

## See also

[USAGE](USAGE.md) · [CONFIGURATION](CONFIGURATION.md) · [CONTRIBUTING](CONTRIBUTING.md) · [RELEASE](RELEASE.md) · [SPEC-KIT](SPEC-KIT.md)
