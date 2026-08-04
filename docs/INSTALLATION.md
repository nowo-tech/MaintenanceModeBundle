# Installation

## Table of contents

- [Requirements](#requirements)
- [Composer](#composer)
- [Enable the bundle](#enable-the-bundle)
  - [With Symfony Flex](#with-symfony-flex)
  - [Without Flex](#without-flex)
- [Next steps](#next-steps)

## Requirements

- PHP `>=8.2` (<8.6). Symfony **8.0** and **8.1** require **PHP 8.4+**.
- Symfony **7.4**, **8.0**, or **8.1** (`^7.4 || ^8.0`).
- `symfony/twig-bundle` (or `twig/twig`) to render the public maintenance page and the admin panel.
- `symfony/security-bundle` when the panel is enabled and `security.allow_unauthenticated` is `false` (default). Also used if you replace `MaintenanceAccessGateInterface` / `access_checker` with your own services. For trusted local demos only, set `allow_unauthenticated: true` to skip this requirement.

## Composer

```bash
composer require nowo-tech/maintenance-mode-bundle
```

## Enable the bundle

### With Symfony Flex

If Symfony Flex is available, the Flex recipe under `.symfony/recipe/nowo-tech/maintenance-mode-bundle/` registers the bundle, copies `config/packages/nowo_maintenance_mode.yaml`, and adds `config/routes/nowo_maintenance_mode.yaml`. Adjust values as needed (see [Configuration](CONFIGURATION.md)).

### Without Flex

1. Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Nowo\MaintenanceModeBundle\NowoMaintenanceModeBundle::class => ['all' => true],
];
```

2. Create `config/packages/nowo_maintenance_mode.yaml`:

```yaml
nowo_maintenance_mode:
    enabled: true
    default_message: "We're making a few gentle improvements. Everything you care about is safe."
    panel:
        enabled: true
        path_prefix: '/_maintenance'
    # security:
    #     password_protection: true
    #     password_hash: '%env(MAINTENANCE_PASSWORD_HASH)%'
```

3. Import panel + preview routes in `config/routes.yaml`:

```yaml
nowo_maintenance_mode:
    resource: '@NowoMaintenanceModeBundle/Resources/config/routes.yaml'
```

This registers the admin panel under `panel.path_prefix` and the dev preview at `preview.path` (default `/_maintenance_preview`).

4. Ensure the app has a writable directory for state/history (defaults under `%kernel.project_dir%/var/maintenance/`).

5. Generate a password hash (never store plaintext):

```bash
php bin/console nowo:maintenance-mode:hash-password
# or: php bin/console nowo:maintenance-mode:hash-password 'your-secret'
# optional: --algo=argon2id
```

Put the printed hash in an env var and reference it as `password_hash: '%env(MAINTENANCE_PASSWORD_HASH)%'`.

## Twig Extra Bundle (REQ-TWIG-004)

This package ships Twig templates. Host applications **must** install and enable Twig Extra:

```bash
composer require twig/extra-bundle twig/string-extra
```

Register `Twig\Extra\TwigExtraBundle\TwigExtraBundle` in `config/bundles.php` (Flex usually does this). Demos already include the same stack. The package `release-check` runs `make check-twig-extra` to guard this contract.

## Next steps

- [Configuration](CONFIGURATION.md)
- [Usage](USAGE.md)
- [Security](SECURITY.md)
