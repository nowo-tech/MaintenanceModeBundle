<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Support\HostPorts;

use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessContext;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenancePanelAccessGateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Host gate in the middle of a migration: implements the deprecated and the framework-free port. Denies everything.
 */
final class DualPortGate implements MaintenanceAccessGateInterface, MaintenancePanelAccessGateInterface
{
    public function isPasswordRequired(): bool
    {
        return true;
    }

    public function isGranted(Request $request): bool
    {
        return false;
    }

    public function authenticate(Request $request, string $password): bool
    {
        return false;
    }

    public function revoke(Request $request): void
    {
    }

    public function isGrantedFor(MaintenanceAccessContext $context): bool
    {
        return false;
    }

    public function isLockedOutFor(MaintenanceAccessContext $context): bool
    {
        return false;
    }

    public function authenticateFor(MaintenanceAccessContext $context, string $password): bool
    {
        return false;
    }

    public function revokeFor(MaintenanceAccessContext $context): void
    {
    }
}
