# Demo applications with FrankenPHP (development and production)

This document describes how the Maintenance Mode Bundle demo runs under **FrankenPHP** in Docker, and how to reproduce **development** and **production**-like configurations.

## Table of contents

- [Overview](#overview)
- [What the demos include](#what-the-demos-include)
- [Layout](#layout)
- [Quick start](#quick-start)
- [Development configuration](#development-configuration)
- [Production configuration](#production-configuration)
- [Switching classic vs worker (`FRANKENPHP_MODE`)](#switching-classic-vs-worker-frankenphp_mode)
- [PHP version (Symfony 8)](#php-version-symfony-8)
- [Bundle sync (REQ-DEMO-007)](#bundle-sync-req-demo-007)
- [Parent demo Makefile](#parent-demo-makefile)
- [Troubleshooting](#troubleshooting)

---

## Overview

**The `demo/` folder is not shipped** when the bundle is installed via Composer (`archive.exclude` in `composer.json`). Demos exist only in the source repository for development, testing, and documentation.

The demos use:

- **FrankenPHP** (Caddy + PHP) in a single container.
- **Docker Compose** mounting the app and the parent bundle (`../..` → `/var/maintenance-mode-bundle`).
- **Two Caddyfiles**: `Caddyfile` (worker mode) and `Caddyfile.dev` (classic / no long-lived worker).
- An **entrypoint** that selects classic vs worker from **`FRANKENPHP_MODE`** (`classic` \| `worker`, default **`worker`** in `.env.example`).

| Aspect | Development (`classic` or hot-reload friendly) | Production-like (`worker`, default) |
|--------|-----------------------------------------------|--------------------------------------|
| FrankenPHP worker | Off (`Caddyfile.dev`) | On (`worker /app/public/index.php 2`) |
| Twig / OPcache | Dev mounts (`php-dev.ini`) | Image defaults |
| `APP_ENV` / `APP_DEBUG` | `dev` / `1` | `prod` / `0` |

---

## What the demos include

- **Symfony Web Profiler** and **DebugBundle** (dev/test).
- **Nowo Twig Inspector** for template debugging.
- **Maintenance Mode Bundle** under test (path repository + volume).
- Home, panel (`/_maintenance`), examples gallery, bypass guide, and preview route.

---

## Layout

| Demo | Symfony | Default host port | Runtime |
| --- | --- | --- | --- |
| `demo/symfony8` | 8.x | **8055** | FrankenPHP + Caddy (**PHP 8.5**) |

---

## Quick start

```bash
make -C demo/symfony8 up   # Symfony 8 — http://localhost:8055
```

- Home: `/`
- Panel: `/_maintenance` (auto-excluded from 503; login toggled by `security.password_protection`)
- Bypass guide: `/examples/bypass`
- Examples gallery: `/examples` (framework themes; `?_locale=es`)
- Current page preview (dev): `/_maintenance_preview`
- Demo panel password when protection is on: `maintenance` (bcrypt hash in config — demo only)

---

## Development configuration

Goal: code and Twig changes are visible without fighting long-lived workers.

1. **Caddyfile.dev** — plain `php_server` (no worker) + cache-busting headers when `FRANKENPHP_MODE=classic`.
2. **php-dev.ini** — `opcache.revalidate_freq=0` mounted into the container.
3. **docker-compose.yml** — `APP_ENV=dev`, `APP_DEBUG=1`, DNS for Packagist, bundle volume.
4. Start: `make -C demo/symfony8 up` (copies `.env.example` → `.env` if missing, `up -d`, `sleep 5`, `composer install`, prints `Demo started at:`).

---

## Production configuration

Use the default **Caddyfile** with `worker /app/public/index.php 2`. Set `APP_ENV=prod` and `APP_DEBUG=0`. Do not mount `php-dev.ini`. Prefer `FRANKENPHP_MODE=worker` (default).

---

## Switching classic vs worker (`FRANKENPHP_MODE`)

| Value | Behaviour |
| --- | --- |
| `worker` (default) | Persistent workers (`php_server { worker /app/public/index.php 2 }`) |
| `classic` | Entrypoint copies `Caddyfile.dev` (per-request PHP) |

`FRANKENPHP_MODE` is defined in `.env` / `.env.example` and passed by Compose — **not** baked as a Dockerfile `ENV`. After changing the value, **recreate** the container:

```bash
docker compose up -d
```

A plain `restart` does not reload env substitution.

---

## PHP version (Symfony 8)

The Symfony 8 demo uses the newest FrankenPHP PHP image allowed by constraints (**PHP 8.5**, `dunglas/frankenphp:1-php8.5`) per REQ-DEMO-010.

---

## Bundle sync (REQ-DEMO-007)

```bash
make -C demo/symfony8 update-bundle
```

Compose mounts the bundle root at `/var/maintenance-mode-bundle` (Composer path repository). `release-check` / demo tests run `update-bundle` before PHPUnit.

---

## Parent demo Makefile

```bash
make -C demo help
make -C demo up-symfony8
make -C demo test-symfony8
make -C demo demo-smoke   # REQ-TEST-011: up → HTTP 200 → down
make -C demo release-check
```

---

## Troubleshooting

| Symptom | Likely cause | Fix |
| --- | --- | --- |
| `composer` cannot resolve `repo.packagist.org` | Docker/WSL DNS | Compose sets `dns: 8.8.8.8` / `8.8.4.4`; recreate container |
| Mode change ignored | Env not reloaded | `docker compose up -d` (recreate), not only `restart` |
| Waiting forever for vendor | `composer install` not run | `make up` or `make install` in the demo folder |
| Port already in use | Another service on `PORT` | Change `PORT` in `.env` and recreate |
