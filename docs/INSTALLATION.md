# Installation

## Requirements

- PHP `>=8.2` (<8.6). Symfony **8.0** and **8.1** require **PHP 8.4+**.
- Symfony **7.4**, **8.0**, or **8.1** (mandatory minimum minors). The bundle also supports Symfony 7.0–7.3 when constraints resolve.
- `symfony/twig-bundle` (or `twig/twig`) to render the public maintenance page and the admin panel.
- Optional: `symfony/security-bundle` if you replace `MaintenanceAccessGateInterface` with your own voter / authenticator.

## Composer

```bash
composer require nowo-tech/maintenance-mode-bundle
```

## Enable the bundle

### With Symfony Flex

The recipe enables the bundle and adds `config/packages/nowo_maintenance_mode.yaml`.

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
    default_message: 'The site is temporarily unavailable for maintenance.'
    panel:
        enabled: true
        path_prefix: '/_maintenance'
    # security:
    #     password_protection: true
    #     password_hash: '%env(MAINTENANCE_PASSWORD_HASH)%'
```

3. Import panel routes in `config/routes.yaml`:

```yaml
nowo_maintenance_mode:
    resource: '@NowoMaintenanceModeBundle/Resources/config/routes.yaml'
```

4. Ensure the app has a writable directory for state/history (defaults under `%kernel.project_dir%/var/maintenance/`).

5. Generate a password hash (never store plaintext):

```bash
php -r 'echo password_hash("your-secret", PASSWORD_BCRYPT), PHP_EOL;'
```

Put the hash in an env var and reference it as `password_hash: '%env(MAINTENANCE_PASSWORD_HASH)%'`.

## Next steps

- [Configuration](CONFIGURATION.md)
- [Usage](USAGE.md)
- [Security](SECURITY.md)
