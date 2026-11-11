<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security;

use Nowo\MaintenanceModeBundle\Security\Http\MaintenanceAccessContextFactory;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
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
 *
 * Implements the framework-free {@see MaintenancePanelAccessGateInterface} (used by the panel since 1.17) and, for BC,
 * the deprecated `Request`-typed {@see MaintenanceAccessGateInterface}: the `Request` methods build a
 * {@see MaintenanceAccessContext} with {@see MaintenanceAccessContextFactory} and delegate, so both give the same
 * answer for the same request (same session keys, same per-IP cache key from `Request::getClientIp()`).
 */
final class PasswordMaintenanceAccessGate implements MaintenancePanelAccessGateInterface, MaintenanceAccessGateInterface
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

    public function isGrantedFor(MaintenanceAccessContext $context): bool
    {
        if (!$this->isPasswordRequired()) {
            return true;
        }

        $session = $context->session;
        if (!$session instanceof MaintenanceAccessSessionInterface) {
            return false;
        }

        return $session->get(self::SESSION_KEY) === true;
    }

    /**
     * Whether too many failed attempts were recorded for this session or client IP.
     */
    public function isLockedOutFor(MaintenanceAccessContext $context): bool
    {
        if (!$this->isPasswordRequired() || $this->maxAttempts < 1) {
            return false;
        }

        return count($this->sessionFailures($context)) >= $this->maxAttempts
            || count($this->cacheFailures($context)) >= $this->maxAttempts;
    }

    public function authenticateFor(MaintenanceAccessContext $context, string $password): bool
    {
        if (!$this->isPasswordRequired()) {
            return true;
        }

        if ($this->isLockedOutFor($context)) {
            $this->logger?->warning('Maintenance panel login refused: too many failed attempts.', ['bundle' => 'maintenance_mode', 'action' => 'login_locked_out']);

            return false;
        }

        /** @var non-empty-string $hash */
        $hash = $this->passwordHash;
        if (!password_verify($password, $hash)) {
            $this->recordFailure($context);
            $this->logger?->warning('Maintenance panel login failed.', ['bundle' => 'maintenance_mode', 'action' => 'login_failed']);

            return false;
        }

        $session = $context->session;
        if ($session instanceof MaintenanceAccessSessionInterface) {
            $session->migrate(true);
            $session->remove(self::SESSION_FAILURES_KEY);
            $session->set(self::SESSION_KEY, true);
        }
        $this->clearCacheFailures($context);
        $this->logger?->info('Maintenance panel login succeeded.', ['bundle' => 'maintenance_mode', 'action' => 'login']);

        return true;
    }

    public function revokeFor(MaintenanceAccessContext $context): void
    {
        $context->session?->remove(self::SESSION_KEY);
    }

    /**
     * @deprecated since 1.17, use {@see self::isGrantedFor()}; removed in 2.0
     */
    public function isGranted(Request $request): bool
    {
        return $this->isGrantedFor(MaintenanceAccessContextFactory::fromRequest($request));
    }

    /**
     * Whether too many failed attempts were recorded for this session or client IP.
     *
     * @deprecated since 1.17, use {@see self::isLockedOutFor()}; removed in 2.0
     */
    public function isLockedOut(Request $request): bool
    {
        return $this->isLockedOutFor(MaintenanceAccessContextFactory::fromRequest($request));
    }

    /**
     * @deprecated since 1.17, use {@see self::authenticateFor()}; removed in 2.0
     */
    public function authenticate(Request $request, string $password): bool
    {
        return $this->authenticateFor(MaintenanceAccessContextFactory::fromRequest($request), $password);
    }

    /**
     * @deprecated since 1.17, use {@see self::revokeFor()}; removed in 2.0
     */
    public function revoke(Request $request): void
    {
        $this->revokeFor(MaintenanceAccessContextFactory::fromRequest($request));
    }

    /**
     * Current Unix time from the injected clock (REQ-DI-001), system time when none is wired.
     */
    private function timestamp(): int
    {
        return $this->clock?->now()->getTimestamp() ?? time();
    }

    private function recordFailure(MaintenanceAccessContext $context): void
    {
        $now     = $this->timestamp();
        $session = $context->session;
        if ($session instanceof MaintenanceAccessSessionInterface) {
            $failures   = $this->sessionFailures($context);
            $failures[] = $now;
            $session->set(self::SESSION_FAILURES_KEY, $failures);
        }

        if (!$this->cache instanceof CacheItemPoolInterface) {
            return;
        }

        try {
            $item       = $this->cache->getItem($this->cacheKey($context));
            $failures   = $this->cacheFailures($context);
            $failures[] = $now;
            $this->cache->save($item->set($failures)->expiresAfter($this->lockoutSeconds));
        } catch (Throwable) {
            // Cache failures must not break the login form; the session counter still applies.
        }
    }

    /**
     * @return list<int>
     */
    private function sessionFailures(MaintenanceAccessContext $context): array
    {
        $session = $context->session;
        if (!$session instanceof MaintenanceAccessSessionInterface) {
            return [];
        }

        return $this->recent($session->get(self::SESSION_FAILURES_KEY, []));
    }

    /**
     * @return list<int>
     */
    private function cacheFailures(MaintenanceAccessContext $context): array
    {
        if (!$this->cache instanceof CacheItemPoolInterface) {
            return [];
        }

        try {
            $item = $this->cache->getItem($this->cacheKey($context));

            return $item->isHit() ? $this->recent($item->get()) : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function clearCacheFailures(MaintenanceAccessContext $context): void
    {
        try {
            $this->cache?->deleteItem($this->cacheKey($context));
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

    /**
     * Same key as 1.x: sha256 of the client IP the edge resolved (`(string) null` = '' when unknown).
     */
    private function cacheKey(MaintenanceAccessContext $context): string
    {
        return 'nowo_maintenance_login_' . hash('sha256', (string) $context->clientIp);
    }
}
