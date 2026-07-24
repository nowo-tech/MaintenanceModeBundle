# Upgrading

## To 1.1.0

Minor release: new ops CLI, exclusions, preview route, events, Twig helpers, and page examples. Requirements are unchanged.

### Requirements

Same as 1.0.0:

- **PHP** `>=8.2` and `<8.6`. Symfony **8.x** still needs **PHP 8.4+**.
- **Symfony** `^7.0 || ^8.0` (CI minors: **7.4**, **8.0**, **8.1**).

### Install / update

```bash
composer require nowo-tech/maintenance-mode-bundle:^1.1
php bin/console cache:clear
```

Ensure routes still import `@NowoMaintenanceModeBundle/Resources/config/routes.yaml` (panel + **`/_maintenance_preview`**).

### Behaviour changes (non-breaking for most apps)

| Topic | 1.0.0 | 1.1.0 |
| --- | --- | --- |
| Default `subscriber_priority` | `32` | `31` (after the router so route / `#[ExcludeFromMaintenance]` work) |
| Request listener registration | `EventSubscriberInterface` + tag priority was easy to miss | `kernel.event_listener` with configured priority |
| Public 503 body | HTML only | HTML or JSON when the client prefers JSON |
| `Retry-After` | Fixed `retry_after` | Uses remaining time until `scheduledDisableAt` when set |

If you relied on the listener running **before** the router, set explicitly:

```yaml
nowo_maintenance_mode:
    subscriber_priority: 32   # or higher
```

### New optional config (defaults are safe)

```yaml
nowo_maintenance_mode:
    preview:
        enabled: ~                    # null = kernel.debug
        path: '/_maintenance_preview'
    exclusions:
        ips: []
    security:
        bypass_token: null
        # bypass_query_parameter / bypass_cookie_name / bypass_set_cookie
```

### New console commands

```bash
php bin/console nowo:maintenance-mode:hash-password
php bin/console nowo:maintenance-mode:enable -m "Deploy" --until="2026-07-24T18:00:00+00:00"
php bin/console nowo:maintenance-mode:status   # exit 2 when effectively on
php bin/console nowo:maintenance-mode:disable
```

### After upgrading

1. `php bin/console cache:clear`
2. In **dev**, open `/_maintenance_preview` (like `/_error/503`) to verify `templates.page`.
3. Smoke-test `/_maintenance` and a non-excluded public route with maintenance enabled (expect HTTP **503**).
4. If you use custom listeners on maintenance state, subscribe to the new domain events as needed.

### Breaking changes

None for public PHP APIs. Config keys from 1.0.0 remain valid.

---

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
