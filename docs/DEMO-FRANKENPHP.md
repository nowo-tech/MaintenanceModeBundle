# Demo (FrankenPHP)

## Layout

| Demo | Symfony | Default host port | Runtime |
| --- | --- | --- | --- |
| `demo/symfony8` | 8.1 | **8055** | FrankenPHP + Caddy (PHP 8.5) |

## Quick start

```bash
make -C demo/symfony8 up   # Symfony 8 — http://localhost:8055
```

- Home: `/`
- Panel: `/_maintenance`
- Demo panel password: `maintenance` (bcrypt hash in config)

## FrankenPHP modes (REQ-DEMO-010)

`FRANKENPHP_MODE` defaults to **`worker`**.

| Value | Behaviour |
| --- | --- |
| `worker` | Persistent worker (`php_server { worker … }`) |
| `classic` | Classic `php_server` (entrypoint copies `Caddyfile.dev`) |

After changing the mode in `.env`, recreate the container:

```bash
docker compose up -d
```

## Bundle sync (REQ-DEMO-007)

```bash
make -C demo/symfony8 update-bundle
```

The compose file mounts the bundle root at `/var/maintenance-mode-bundle` (Composer path repository + symlink).

## Parent demo Makefile

```bash
make -C demo help
make -C demo up-symfony8
make -C demo test-symfony8
make -C demo release-check
```
