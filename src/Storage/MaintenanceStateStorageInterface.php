<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Storage;

use Nowo\MaintenanceModeBundle\Model\MaintenanceState;

interface MaintenanceStateStorageInterface
{
    public function load(): MaintenanceState;

    public function save(MaintenanceState $state): void;
}
