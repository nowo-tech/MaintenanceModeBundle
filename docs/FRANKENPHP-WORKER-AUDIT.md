# FrankenPHP worker mode audit (kernel not reset between requests)

| Field | Value |
|-------|-------|
| Package | `nowo-tech/maintenance-mode-bundle` (`symfony-bundle`) |
| Audited revision | `v1.5.8` |
| Audit date | 2026-09-24 |
| Method | Manual review of every file under `src/` (services, request listener, storages, controllers, forms, commands, Twig extension, DI extension, compiler pass, `Resources/config`) plus regression tests that re-use the same instances across consecutive requests without `reset()` |
| **Verdict** | ✅ **100% compatible under scenario B** (`reset_kernel false`) — all HTTP services are stateless (`readonly` config only); maintenance state is re-read from storage on every request; auth / bypass decisions live on the current `Request` / session only |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the **strict** variant: the kernel is **not** rebooted between requests (`reset_kernel false`), so every shared service, static property and PHP global survives from one request to the next. Two scenarios are evaluated:

- **A — kernel not rebooted, `services_resetter` still runs:** services tagged `kernel.reset` (or implementing `ResetInterface`) are reset between requests.
- **B — no reset at all:** nothing is reset; any per-request state kept in a service leaks into the next request.

A bundle that is safe under **B** is safe under **A** and under classic mode / PHP-FPM.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | Every shared service only has `private readonly` constructor properties (config scalars/arrays and service references). `ConfigurableMaintenanceModeAccessChecker` is a `final readonly` class |
| Static properties / `static` locals | ✅ | None; only pure `static` factories on models (`MaintenanceState::fromArray()`, `MaintenanceHistoryEntry::fromArray()`) |
| `ResetInterface` / `kernel.reset` coverage | ✅ N/A | Nothing to reset — no per-request memoization in bundle services |
| Request / user / locale captured in services | ✅ | `Request` is a method argument; bypass cookie token travels in request attributes, not in the listener |
| Superglobals, `$_ENV`, `putenv`, `ini_set`, `setlocale`, timezone | ✅ | None used; config is compiled into container arguments |
| Doctrine / EntityManager | ✅ N/A | No persistence layer; default storage is the filesystem |
| Output, headers, `exit`, shutdown functions | ✅ | None; headers and cookies are set on `Response` objects |
| Resources (files, sockets, cURL) held open | ✅ | Files are opened and closed inside each call (`file_get_contents` / `file_put_contents`) |
| Memory growth across requests | ✅ | No caches or accumulating arrays in services |
| Blocking I/O and timeouts | ⚠️ Low | Filesystem read on every main request; history view loads the whole JSONL file |
| Third-party static state | ✅ | Only `Symfony\Component\Yaml\Yaml::parse()/dump()` (stateless) when a `.yaml` state file is used |
| PHPStan FrankenPHP rulesets | ✅ | `ruleset-classic.neon` + `ruleset-worker.neon` included in `phpstan.neon.dist` |

Worker demo: `demo/symfony8/docker/frankenphp/Caddyfile` declares a `worker` block. Regression: `tests/Unit/WorkerMode/WorkerModeNoKernelResetTest.php`.

## Services reviewed

| Service | Shared | Mutable state | Scenario A | Scenario B |
|---------|--------|---------------|------------|------------|
| `EventSubscriber\MaintenanceRequestSubscriber` (`kernel.request` / `kernel.response` listener) | yes | none (`readonly` config + references) | ✅ | ✅ |
| `Service\MaintenanceManager` | yes | none; state is loaded from storage on each call | ✅ | ✅ |
| `Storage\FilesystemMaintenanceStateStorage` | yes | none (`readonly` `$filePath`) | ✅ | ✅ |
| `Storage\FilesystemMaintenanceHistoryStorage` | yes | none (`readonly` `$filePath`) | ✅ | ✅ |
| `Exclusion\MaintenanceExclusionMatcher` | yes | none (`readonly` lists) | ✅ | ✅ |
| `Security\PasswordMaintenanceAccessGate` | yes | none; authorization flag lives in the user session | ✅ | ✅ |
| `nowo_maintenance_mode.access_checker.default` (`ConfigurableMaintenanceModeAccessChecker`) / `.allow_all` | yes | none (`final readonly`) | ✅ | ✅ |
| `Twig\MaintenanceExtension` | yes | none; globals are compile-time config strings | ✅ | ✅ |
| `Controller\MaintenancePanelController` | yes (public) | none | ✅ | ✅ |
| `Controller\MaintenancePreviewController` | yes (public) | none | ✅ | ✅ |
| Form types (`Form\*Type`) | yes | stateless | ✅ | ✅ |
| Console commands (`Command\*Command`) | CLI only | none | N/A | N/A |

`Model\MaintenanceState` is a mutable class, but its `with*()` methods clone, and instances are created per call by `FilesystemMaintenanceStateStorage::load()`; no service stores one in a property. `TwigPathsPass` and `MaintenanceModeExtension` only run at container compile time.

## Findings

### W-01 — Maintenance state is read from disk on every main request (Low, accepted)

- **Where:** `MaintenanceRequestSubscriber` → `MaintenanceManager::getState()` → `FilesystemMaintenanceStateStorage::load()`. Twig helpers `nowo_maintenance_is_enabled()` / `nowo_maintenance_state()` trigger the same read.
- **Worker impact:** no state leak. This is the correct design for a long-lived worker: toggling maintenance from the CLI or from another worker is visible on the next request without restarting workers, because nothing is memoized in the process. The cost is one small file read per request (plus YAML parsing if the state file ends in `.yaml`/`.yml`). On slow or network filesystems this blocks the worker thread for the duration of the read, with no timeout.
- **Recommendation:** keep `storage.state_file` on local disk (default `%kernel.project_dir%/var/maintenance/state.json`) and prefer JSON. Do **not** add an in-process cache of the state without a short TTL or a `ResetInterface`, otherwise workers would serve a stale on/off decision under scenario B.
- **Status:** Accepted — by design. Covered by `WorkerModeNoKernelResetTest`.

### W-02 — History view loads the whole JSONL file into memory (Low, accepted)

- **Where:** `FilesystemMaintenanceHistoryStorage::list()` (`file_get_contents` of the full file). Called from the panel history action (limit 100). `append()` never rotates the file.
- **Worker impact:** memory is only used for the duration of the panel request and is released afterwards, so nothing accumulates across requests. A very large history file can spike peak RSS; the PHP allocator may keep that peak for the lifetime of the worker process.
- **Recommendation:** rotate or truncate `history.jsonl` periodically, or plug a custom `storage.history_storage` backed by a database with `LIMIT`.
- **Status:** Accepted — documented; not a cross-request leak.

### W-03 — Concurrent writes across worker threads are handled correctly (Info)

- **Where:** `FilesystemMaintenanceStateStorage::save()` writes to a temp file then `rename()`s; history uses `FILE_APPEND | LOCK_EX`.
- **Worker impact:** readers in other worker threads see either the old or the new state file, never a partial one. Two simultaneous panel writes are last-writer-wins (same as PHP-FPM).
- **Recommendation:** none.

### W-04 — Access decisions are computed per request (Info)

- **Where:** `PasswordMaintenanceAccessGate` reads the session flag on the current request; `ConfigurableMaintenanceModeAccessChecker` asks `AuthorizationCheckerInterface::isGranted()` each time; the bypass token is compared against the current query/cookie and handed to `onKernelResponse()` only through `$request->attributes`.
- **Worker impact:** no decision or token is cached in a service, so one user's panel login or bypass cannot be reused by another user on the same worker.
- **Recommendation:** none. Covered by `WorkerModeNoKernelResetTest`.

No open remediations.

## Usage recommendations in worker mode

- No special configuration or reset hook is required for this bundle under `reset_kernel false`.
- Enable or disable maintenance with `nowo:maintenance-mode:enable` / `disable` or the panel; running workers pick the change up on the next request. There is no need to restart FrankenPHP.
- Custom `storage.state_storage`, `storage.history_storage`, `security.access_gate` or `security.access_checker` services must stay **stateless**, or implement `ResetInterface` and rely on scenario A. In particular, do not cache the `MaintenanceState` or a "granted" flag in a property: under scenario B it would be served to every following request and user.
- Keep the state file on a local filesystem and rotate the history file if the panel is used heavily.

## Re-audit triggers

Re-run this audit when a change adds: properties that are written after construction in any service, a cache of the maintenance state, a new storage backend (Doctrine, Redis, HTTP), an event listener that collects data, or any use of `$_SERVER` / `$_ENV` / `header()` at runtime.
