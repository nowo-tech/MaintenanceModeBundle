# Spec-driven development

## Table of contents

- [Three layers in sync](#three-layers-in-sync)
- [GitHub Spec Kit baseline](#github-spec-kit-baseline)
- [User stories](#user-stories)
- [Functional scope](#functional-scope)
- [Validating the functional spec](#validating-the-functional-spec)
- [Requirement identifiers](#requirement-identifiers-selected)
- [Contributor workflow](#contributor-workflow)
- [Relationship to Engram](#relationship-to-engram)
- [See also](#see-also)

## Three layers in sync

1. **Spec Kit baseline** — [`specs/001-baseline/`](../specs/001-baseline/) records
   the current product, functional requirements, success criteria, and a complete
   `src/` inventory.
2. **Product behaviour** — what consuming applications may rely on: HTTP 503
   maintenance, exclusions, panel, schedules, and pluggable storage/gates.
   Documented in [USAGE.md](USAGE.md) and [CONFIGURATION.md](CONFIGURATION.md).
3. **REQ-* anchors** — identifiers that tie repository policies and implementation
   constraints to their source files and validation, such as `REQ-CS-005`,
   `REQ-DEMO-010`, and `REQ-MAKE-001`.

All three layers must remain aligned.

## GitHub Spec Kit baseline

GitHub Spec Kit is the repository workflow for maintaining the baseline and
incremental feature specifications. Read [SPEC-KIT.md](SPEC-KIT.md) for CLI
installation, initialization, and Cursor Agent skills. The current baseline is
[`specs/001-baseline/`](../specs/001-baseline/), including its
[`spec.md`](../specs/001-baseline/spec.md) and
[`code-inventory.md`](../specs/001-baseline/code-inventory.md).

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

1. Clarify the behaviour and applicable REQ-* anchors.
2. Implement with tests.
3. Update integrator documentation and configuration guidance when needed.
4. When `src/` changes, update the baseline inventory and its FR-* mapping.
5. Run `make release-check`.

## Relationship to Engram

See [ENGRAM.md](ENGRAM.md) for AI memory; this file owns product behaviour and REQ traceability.

## See also

[USAGE](USAGE.md) · [CONFIGURATION](CONFIGURATION.md) ·
[CONTRIBUTING](CONTRIBUTING.md) · [RELEASE](RELEASE.md) ·
[SPEC-KIT](SPEC-KIT.md) ·
[Baseline spec](../specs/001-baseline/spec.md) ·
[Baseline code inventory](../specs/001-baseline/code-inventory.md)
