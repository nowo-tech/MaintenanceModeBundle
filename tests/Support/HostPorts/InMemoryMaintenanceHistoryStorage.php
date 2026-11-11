<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Support\HostPorts;

use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface;

use function array_reverse;
use function array_slice;

/**
 * Host-provided history storage.
 */
final class InMemoryMaintenanceHistoryStorage implements MaintenanceHistoryStorageInterface
{
    /** @var list<MaintenanceHistoryEntry> */
    private array $entries = [];

    public function append(MaintenanceHistoryEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    public function list(int $limit = 50): array
    {
        return array_slice(array_reverse($this->entries), 0, $limit);
    }
}
