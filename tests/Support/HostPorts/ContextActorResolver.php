<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Support\HostPorts;

use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessContext;
use Nowo\MaintenanceModeBundle\Security\MaintenanceActorResolverInterface;

/**
 * DDD host actor resolver: implements only the framework-free port.
 */
final readonly class ContextActorResolver implements MaintenanceActorResolverInterface
{
    public function resolveActorFor(MaintenanceAccessContext $context): string
    {
        return 'host-ip:' . $context->clientIp;
    }
}
