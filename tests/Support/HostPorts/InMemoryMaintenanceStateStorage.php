<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Support\HostPorts;

use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;

/**
 * Host-provided state storage (DDD host: the adapter lives in the host's Infrastructure, e.g. on its own table).
 */
final class InMemoryMaintenanceStateStorage implements MaintenanceStateStorageInterface
{
    private ?MaintenanceState $state = null;

    public int $saves = 0;

    public function load(): MaintenanceState
    {
        return $this->state ?? new MaintenanceState();
    }

    public function save(MaintenanceState $state): void
    {
        $this->state = $state;
        ++$this->saves;
    }
}
