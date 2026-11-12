<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Support\HostPorts;

use Nowo\MaintenanceModeBundle\Security\ActorResolverInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessContext;
use Nowo\MaintenanceModeBundle\Security\MaintenanceActorResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Host actor resolver implementing the deprecated and the framework-free port.
 */
final class DualPortActorResolver implements ActorResolverInterface, MaintenanceActorResolverInterface
{
    public function resolveActor(Request $request): string
    {
        return 'dual';
    }

    public function resolveActorFor(MaintenanceAccessContext $context): string
    {
        return 'dual';
    }
}
