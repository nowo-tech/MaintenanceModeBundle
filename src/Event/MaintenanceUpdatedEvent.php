<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Event;

use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Symfony\Contracts\EventDispatcher\Event;

final class MaintenanceUpdatedEvent extends Event
{
    public function __construct(
        public readonly MaintenanceState $state,
        public readonly string $action = 'update',
        public readonly ?string $actor = null,
    ) {
    }
}
