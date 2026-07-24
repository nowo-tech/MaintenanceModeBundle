# Upgrading

## To 1.0.0

First public release. No prior Packagist versions.

### Requirements

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** still needs **PHP 8.4+**.
- **Symfony** `^7.0 || ^8.0` (mandatory minors in CI: **7.4**, **8.0**, **8.1**). Symfony **6.4** is not supported.

### Install

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.0
```

With **Symfony Flex**, the recipe registers the bundle and adds config. Without Flex:

1. Register `Nowo\MaintenanceModeBundle\NowoMaintenanceModeBundle` in `config/bundles.php`.
2. Import routes: `@NowoMaintenanceModeBundle/Resources/config/routes.yaml` (or the recipe file under `config/routes/`).
3. Ensure `var/maintenance/` is writable (or override `storage.state_file` / `history_file`).
4. Prefer a hashed panel password via env: `password_hash: '%env(MAINTENANCE_PASSWORD_HASH)%'` (never store plaintext).

### Breaking changes

None (initial release).

### Storage backends

Switching from filesystem to a custom Doctrine (or other) backend: implement the storage interfaces and set `storage.state_storage` / `storage.history_storage`. Migrate existing JSON/JSONL files manually if you need history continuity.

### After upgrading

```bash
php bin/console cache:clear
```

Smoke-test `/_maintenance` and a non-excluded public route while maintenance is enabled (expect HTTP **503**).
