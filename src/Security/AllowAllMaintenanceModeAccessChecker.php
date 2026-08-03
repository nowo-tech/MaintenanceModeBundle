<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security;

/**
 * Used when security.allow_unauthenticated is true (local demos / trusted networks only).
 */
final class AllowAllMaintenanceModeAccessChecker implements MaintenanceModeAccessCheckerInterface
{
    public function canAccess(?object $user): bool
    {
        return true;
    }
}
