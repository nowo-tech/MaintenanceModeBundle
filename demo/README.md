# Maintenance Mode Bundle demos

## Symfony 8

```bash
make up-symfony8
```

Open http://localhost:8055 — panel at `/_maintenance` (password: `maintenance` when `password_protection: true`).

- Calm page examples: http://localhost:8055/examples
- Exclusion / bypass guide: http://localhost:8055/examples/bypass

Toggle panel login in `demo/symfony8/config/packages/nowo_maintenance_mode.yaml` via `security.password_protection`.

See [docs/DEMO-FRANKENPHP.md](../docs/DEMO-FRANKENPHP.md) and [docs/MAINTENANCE-PAGE-EXAMPLES.md](../docs/MAINTENANCE-PAGE-EXAMPLES.md).
