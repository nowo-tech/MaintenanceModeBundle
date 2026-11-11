<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Support\HostPorts;

use Nowo\MaintenanceModeBundle\Security\ActorResolverInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Host-provided actor resolver (a DDD host maps its own user id here).
 */
final class FixedActorResolver implements ActorResolverInterface
{
    public function resolveActor(Request $request): string
    {
        return 'host-user-42';
    }
}
