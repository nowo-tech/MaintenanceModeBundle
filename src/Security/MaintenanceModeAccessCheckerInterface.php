<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Security;

/**
 * Role / custom access control for the maintenance admin panel (REQ-UI-002).
 * Complements the optional ops password gate ({@see MaintenanceAccessGateInterface}).
 */
interface MaintenanceModeAccessCheckerInterface
{
    public function canAccess(?object $user): bool;
}
