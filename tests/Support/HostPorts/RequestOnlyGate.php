<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Support\HostPorts;

use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Host gate still on the deprecated `Request` port only. Denies everything.
 */
final class RequestOnlyGate implements MaintenanceAccessGateInterface
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
}
