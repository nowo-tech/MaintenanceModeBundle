# Symfony 8 Demo — Maintenance Mode Bundle

FrankenPHP demo: HTTP 503 maintenance + panel at `/_maintenance/` on the UiKit page contract (`templates/base.html.twig` extends UiKit's admin layout and is the host admin layout, UiKit's own guest layout for the 503 page and login, default theme, `config/packages/nowo_ui_kit.yaml`).

## Usage

```bash
make up
```

Open http://localhost:8055

- Panel password: `maintenance`
- Excluded health check: `/health`

See [DEMO-FRANKENPHP.md](../../docs/DEMO-FRANKENPHP.md).
