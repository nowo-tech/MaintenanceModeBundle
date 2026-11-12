<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Support\HostPorts;

use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessContext;
use Nowo\MaintenanceModeBundle\Security\MaintenanceActorResolverInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Host decorator of the framework-free actor resolver only (`#[AsDecorator]` on the new interface).
 */
#[AsDecorator(MaintenanceActorResolverInterface::class)]
final readonly class PrefixingDecoratingActorResolver implements MaintenanceActorResolverInterface
{
    public function __construct(
        #[AutowireDecorated]
        private MaintenanceActorResolverInterface $inner,
    ) {
    }

    public function resolveActorFor(MaintenanceAccessContext $context): string
    {
        return 'host:' . ($this->inner->resolveActorFor($context) ?? 'panel');
    }
}
