<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security\Http;

use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessSessionInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Symfony adapter of {@see MaintenanceAccessSessionInterface}: the request's own session, unchanged keys.
 *
 * @internal built per request by {@see MaintenanceAccessContextFactory}
 */
final readonly class SymfonyMaintenanceAccessSession implements MaintenanceAccessSessionInterface
{
    public function __construct(
        private SessionInterface $session,
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->session->get($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        $this->session->set($key, $value);
    }

    public function remove(string $key): void
    {
        $this->session->remove($key);
    }

    public function migrate(bool $destroy = false): void
    {
        $this->session->migrate($destroy);
    }
}
