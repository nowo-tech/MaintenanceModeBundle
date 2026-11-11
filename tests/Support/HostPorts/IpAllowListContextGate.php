<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Support\HostPorts;

use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessContext;
use Nowo\MaintenanceModeBundle\Security\MaintenancePanelAccessGateInterface;

/**
 * DDD host gate: implements only the framework-free port (no `Request`), grants the panel to one ops address and
 * denies everything else (deny by default).
 */
final readonly class IpAllowListContextGate implements MaintenancePanelAccessGateInterface
{
    public const OPS_IP = '10.0.0.5';

    public function isPasswordRequired(): bool
    {
        return true;
    }

    public function isGrantedFor(MaintenanceAccessContext $context): bool
    {
        return $context->clientIp === self::OPS_IP;
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
