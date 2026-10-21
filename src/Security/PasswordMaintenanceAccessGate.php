<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Throwable;

use function array_filter;
use function array_values;
use function count;
use function hash;
use function is_array;
use function is_int;
use function password_verify;
use function time;

/**
 * Default gate: optional password hash (bcrypt / argon2id / sodium) from config.
 * When no hash is configured (or protection is disabled), access is open.
 *
 * Failed attempts are throttled per session and, when a cache pool is available, per client IP:
 * after `maxAttempts` failures within `lockoutSeconds` the password is not even checked.
 * A successful login migrates the session id (session fixation).
 */
final class PasswordMaintenanceAccessGate implements MaintenanceAccessGateInterface
{
    public const SESSION_KEY = '_nowo_maintenance_mode_authorized';

    public const SESSION_FAILURES_KEY = '_nowo_maintenance_mode_login_failures';

    public function __construct(
        private readonly ?string $passwordHash,
        private readonly bool $enabled = true,
        private readonly int $maxAttempts = 5,
        private readonly int $lockoutSeconds = 900,
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly ?ClockInterface $clock = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function isPasswordRequired(): bool
    {
        return $this->enabled && $this->passwordHash !== null && $this->passwordHash !== '';
    }

    public function isGranted(Request $request): bool
    {
        if (!$this->isPasswordRequired()) {
            return true;
        }

        $session = $request->hasSession() ? $request->getSession() : null;
        if (!$session instanceof SessionInterface) {
            return false;
        }

        return $session->get(self::SESSION_KEY) === true;
    }

    /**
     * Whether too many failed attempts were recorded for this session or client IP.
     */
    public function isLockedOut(Request $request): bool
    {
        if (!$this->isPasswordRequired() || $this->maxAttempts < 1) {
            return false;
        }

        return count($this->sessionFailures($request)) >= $this->maxAttempts
            || count($this->cacheFailures($request)) >= $this->maxAttempts;
    }

    public function authenticate(Request $request, string $password): bool
    {
        if (!$this->isPasswordRequired()) {
            return true;
        }

        if ($this->isLockedOut($request)) {
            $this->logger?->warning('Maintenance panel login refused: too many failed attempts.', ['bundle' => 'maintenance_mode', 'action' => 'login_locked_out']);

            return false;
        }

        /** @var non-empty-string $hash */
        $hash = $this->passwordHash;
        if (!password_verify($password, $hash)) {
            $this->recordFailure($request);
            $this->logger?->warning('Maintenance panel login failed.', ['bundle' => 'maintenance_mode', 'action' => 'login_failed']);

            return false;
        }

        if ($request->hasSession()) {
            $session = $request->getSession();
            $session->migrate(true);
            $session->remove(self::SESSION_FAILURES_KEY);
            $session->set(self::SESSION_KEY, true);
        }
        $this->clearCacheFailures($request);
        $this->logger?->info('Maintenance panel login succeeded.', ['bundle' => 'maintenance_mode', 'action' => 'login']);

        return true;
    }

    public function revoke(Request $request): void
    {
        if ($request->hasSession()) {
            $request->getSession()->remove(self::SESSION_KEY);
        }
    }

    /**
     * Current Unix time from the injected clock (REQ-DI-001), system time when none is wired.
     */
    private function timestamp(): int
    {
        return $this->clock?->now()->getTimestamp() ?? time();
    }

    private function recordFailure(Request $request): void
    {
        $now = $this->timestamp();
        if ($request->hasSession()) {
            $failures   = $this->sessionFailures($request);
            $failures[] = $now;
            $request->getSession()->set(self::SESSION_FAILURES_KEY, $failures);
        }

        if (!$this->cache instanceof CacheItemPoolInterface) {
            return;
        }

        try {
            $item       = $this->cache->getItem($this->cacheKey($request));
            $failures   = $this->cacheFailures($request);
            $failures[] = $now;
            $this->cache->save($item->set($failures)->expiresAfter($this->lockoutSeconds));
        } catch (Throwable) {
            // Cache failures must not break the login form; the session counter still applies.
        }
    }

    /**
     * @return list<int>
     */
    private function sessionFailures(Request $request): array
    {
        if (!$request->hasSession()) {
            return [];
        }

        return $this->recent($request->getSession()->get(self::SESSION_FAILURES_KEY, []));
    }

    /**
     * @return list<int>
     */
    private function cacheFailures(Request $request): array
    {
        if (!$this->cache instanceof CacheItemPoolInterface) {
            return [];
        }

        try {
            $item = $this->cache->getItem($this->cacheKey($request));

            return $item->isHit() ? $this->recent($item->get()) : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function clearCacheFailures(Request $request): void
    {
        try {
            $this->cache?->deleteItem($this->cacheKey($request));
        } catch (Throwable) {
        }
    }

    /**
     * @return list<int>
     */
    private function recent(mixed $failures): array
    {
        if (!is_array($failures)) {
            return [];
        }
        $cutoff = $this->timestamp() - $this->lockoutSeconds;

        return array_values(array_filter($failures, static fn (mixed $ts): bool => is_int($ts) && $ts > $cutoff));
    }

    private function cacheKey(Request $request): string
    {
        return 'nowo_maintenance_login_' . hash('sha256', (string) $request->getClientIp());
    }
}
