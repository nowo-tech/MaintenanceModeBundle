# Maintenance Mode Bundle

[![CI](https://github.com/nowo-tech/MaintenanceModeBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/MaintenanceModeBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/maintenance-mode-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/maintenance-mode-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/maintenance-mode-bundle.svg)](https://packagist.org/packages/nowo-tech/maintenance-mode-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/MaintenanceModeBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/MaintenanceModeBundle) [![Coverage](https://img.shields.io/badge/Coverage-100%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** [Install from Packagist](https://packagist.org/packages/nowo-tech/maintenance-mode-bundle) · Give it a **star** on [GitHub](https://github.com/nowo-tech/MaintenanceModeBundle) so more developers can find it.

**Maintenance Mode Bundle** — Put a Symfony site into **maintenance mode** (HTTP **503**) with configurable exclusions, a Twig admin panel, scheduled windows, append-only history, and pluggable storage / access gates. Tested on Symfony **7.4**, **8.0**, and **8.1** (also compatible with Symfony 7.0–7.3) · PHP 8.2+ (Symfony 8.x requires PHP 8.4+).

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**.

## Features

- **503 listener** — Configurable-priority `kernel.request` interceptor (default after router), HTML or JSON, dynamic `Retry-After`, `Cache-Control: no-store`.
- **Exclusions** — Exact paths, prefixes, route names, globs, `#regex#` / `~regex~`, **IPs/CIDR**, `#[ExcludeFromMaintenance]`, soft bypass token.
- **Admin panel** — Enable / disable / schedule / clear schedule / history under a configurable prefix (default `/_maintenance`).
- **CLI** — `enable` / `disable` / `status` / `hash-password` for deploys and ops scripts.
- **Events & Twig** — Domain events on state changes; `nowo_maintenance_is_enabled()` / `nowo_maintenance_state()`.
- **Password gate** — Optional `password_hash` (bcrypt / argon2id); replaceable via `MaintenanceAccessGateInterface`.
- **Pluggable storage** — Filesystem JSON/JSONL by default; swap for Doctrine or anything else via DI.

## Installation

```bash
composer require nowo-tech/maintenance-mode-bundle
```

With **Symfony Flex**, the recipe registers the bundle and adds config. Without Flex, see [docs/INSTALLATION.md](docs/INSTALLATION.md).

```yaml
# config/routes.yaml
nowo_maintenance_mode:
    resource: '@NowoMaintenanceModeBundle/Resources/config/routes.yaml'
```

## Configuration

```yaml
nowo_maintenance_mode:
    enabled: true
    default_message: "We're making a few gentle improvements. Everything you care about is safe."
    panel:
        path_prefix: '/_maintenance'
    security:
        password_protection: true
        password_hash: '%env(MAINTENANCE_PASSWORD_HASH)%'
    exclusions:
        paths: ['/health']
        path_prefixes: ['/api/health']
```

## Usage

Open `/_maintenance` to toggle maintenance. Visitors hit the public page with **HTTP 503**; excluded routes and the panel keep working. In **dev**, preview the configured page at `/_maintenance_preview` (like `/_error/503`). Demo gallery: `/examples`.

```php
$maintenance->enable('Deploy in progress', 'ops');
$maintenance->disable('ops');
```

See [docs/USAGE.md](docs/USAGE.md).

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [Demo (FrankenPHP)](docs/DEMO-FRANKENPHP.md)
- [Maintenance page examples](docs/MAINTENANCE-PAGE-EXAMPLES.md)
- [GitHub Actions CI requirements](docs/GITHUB_CI.md)

## Requirements

- PHP `>=8.2` (<8.6); **Symfony 8.0** and **8.1** require **PHP 8.4+**
- Symfony **7.4**, **8.0**, or **8.1** (minimum supported minors; also works on Symfony 7.0–7.3 via `composer.json` constraints)
- Twig for the maintenance page and panel templates

## Development

```bash
make up
make install
make test
make cs-check
make phpstan
make release-check
```

## Demo

| Demo | Symfony | PHP | Default port |
| --- | --- | --- | --- |
| `demo/symfony8` | **8.1** | 8.5 | **8055** |

Runs **FrankenPHP + Caddy** (`FRANKENPHP_MODE=worker` by default). Panel password: `maintenance`. See [docs/DEMO-FRANKENPHP.md](docs/DEMO-FRANKENPHP.md).

```bash
make -C demo help
make -C demo up-symfony8
```

## Tests and coverage

- Tests: PHPUnit (PHP)
- PHP: **100%** Lines (run `make coverage-check`)

## License and author

MIT · [Nowo.tech](https://nowo.tech) · [Héctor Franco Aceituno](https://github.com/HecFranco)
