<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Support\HostPorts;

use Nowo\MaintenanceModeBundle\Event\MaintenanceDisabledEvent;
use Nowo\MaintenanceModeBundle\Event\MaintenanceEnabledEvent;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;

/**
 * Records, for each event, the actor and whether the host storage already held the new state at dispatch time.
 */
final class MaintenanceEventProbe
{
    /** @var list<array{event: string, actor: ?string, storedEnabled: bool}> */
    public array $seen = [];

    public function __construct(private readonly MaintenanceStateStorageInterface $storage)
    {
    }

    public function onEnabled(MaintenanceEnabledEvent $event): void
    {
        $this->seen[] = ['event' => 'enabled', 'actor' => $event->actor, 'storedEnabled' => $this->storage->load()->isEnabled()];
    }

    public function onDisabled(MaintenanceDisabledEvent $event): void
    {
        $this->seen[] = ['event' => 'disabled', 'actor' => $event->actor, 'storedEnabled' => $this->storage->load()->isEnabled()];
    }
}
