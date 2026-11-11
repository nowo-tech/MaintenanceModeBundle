<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security;

/**
 * The per-visitor session a {@see MaintenancePanelAccessGateInterface} reads and writes (panel authorization flag,
 * failed-attempt counter). Bundle-owned, framework-free: the Symfony adapter is
 * {@see Http\SymfonyMaintenanceAccessSession}; a DDD host may pass its own implementation.
 *
 * One instance belongs to one request: never keep it in a service property (FrankenPHP worker mode, kernel reset false).
 */
interface MaintenanceAccessSessionInterface
{
    public function get(string $key, mixed $default = null): mixed;

    public function set(string $key, mixed $value): void;

    public function remove(string $key): void;

    /**
     * Give the session a new id (session fixation defence after a successful login).
     */
    public function migrate(bool $destroy = false): void;
}
