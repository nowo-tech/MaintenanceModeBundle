<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security;

/**
 * Resolves the actor recorded (history `actor`, state `updatedBy`, event `$actor`) for panel actions, framework-free
 * (REQ-ARCH-001 rule 3). Replaces the `Request`-typed {@see ActorResolverInterface} (deprecated since 1.17, removed
 * in 2.0). When a resolver implements both interfaces, the panel uses this one.
 *
 * The "no person" actor stays `ActorResolverInterface::PANEL` (`panel`) in 1.x: PHP refuses a class that inherits the
 * same constant from two interfaces, so it moves to this interface only in 2.0.
 */
interface MaintenanceActorResolverInterface
{
    /**
     * The actor for this request, or null for none. Keep it to 1-128 printable characters: the panel passes every
     * value through {@see SecurityTokenActorResolver::normalize()} and records "panel" when nothing is left.
     */
    public function resolveActorFor(MaintenanceAccessContext $context): ?string;
}
