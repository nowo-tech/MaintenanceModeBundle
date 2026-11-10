<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves the actor recorded (history `actor`, state `updatedBy`, event `$actor`) for panel actions.
 *
 * Default: {@see StaticActorResolver} ("panel", the 1.x value). {@see SecurityTokenActorResolver} records the
 * logged-in user identifier instead; set `security.actor_resolver` to it or to your own service.
 */
interface ActorResolverInterface
{
    /**
     * Actor that is not a person: the panel when no user is known (1.x value).
     */
    public const PANEL = 'panel';

    public function resolveActor(Request $request): ?string;
}
