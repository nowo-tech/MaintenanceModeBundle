<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Storage;

use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;

interface MaintenanceHistoryStorageInterface
{
    public function append(MaintenanceHistoryEntry $entry): void;

    /**
     * @return list<MaintenanceHistoryEntry>
     */
    public function list(int $limit = 50): array;
}
