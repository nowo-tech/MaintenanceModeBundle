<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Records the logged-in user identifier (`UserInterface::getUserIdentifier()`), or the fallback ("panel") when the
 * request has no authenticated user (password-gate only, or no SecurityBundle).
 */
final readonly class SecurityTokenActorResolver implements ActorResolverInterface
{
    public function __construct(
        private ?TokenStorageInterface $tokenStorage = null,
        private ?string $fallback = self::PANEL,
    ) {
    }

    public function resolveActor(Request $request): ?string
    {
        $identifier = $this->tokenStorage?->getToken()?->getUserIdentifier();

        return $identifier !== null && $identifier !== '' ? $identifier : $this->fallback;
    }
}
