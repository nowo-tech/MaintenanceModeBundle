# Symfony 8 Demo — Maintenance Mode Bundle

FrankenPHP demo: HTTP 503 maintenance + panel at `/_maintenance/` on the UiKit page contract (host layouts `templates/layouts/admin.html.twig` / `guest.html.twig`, theme in `config/packages/nowo_ui_kit.yaml`).

## Usage

```bash
make up
```

Open http://localhost:8055

- Panel password: `maintenance`
- Excluded health check: `/health`

See [DEMO-FRANKENPHP.md](../../docs/DEMO-FRANKENPHP.md).
