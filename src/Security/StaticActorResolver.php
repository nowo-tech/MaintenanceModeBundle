<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * Default actor resolver: always the same label ("panel" unless configured otherwise).
 */
final readonly class StaticActorResolver implements MaintenanceActorResolverInterface, ActorResolverInterface
{
    public function __construct(
        private ?string $actor = self::PANEL,
    ) {
    }

    public function resolveActorFor(MaintenanceAccessContext $context): ?string
    {
        return $this->actor;
    }

    /**
     * @deprecated since 1.17, use {@see self::resolveActorFor()}; removed in 2.0
     */
    public function resolveActor(Request $request): ?string
    {
        return $this->actor;
    }
}
