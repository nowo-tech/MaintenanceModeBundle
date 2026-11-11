<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Support\HostPorts;

use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessContext;
use Nowo\MaintenanceModeBundle\Security\MaintenancePanelAccessGateInterface;

/**
 * Host gate that implements only the framework-free port and records every context it receives.
 */
final class RecordingContextGate implements MaintenancePanelAccessGateInterface
{
    public bool $passwordRequired = false;

    public bool $granted = false;

    public bool $locked = false;

    public bool $authResult = false;

    /** @var list<array{0: string, 1: MaintenanceAccessContext}> */
    public array $contexts = [];

    public function isPasswordRequired(): bool
    {
        return $this->passwordRequired;
    }

    public function isGrantedFor(MaintenanceAccessContext $context): bool
    {
        $this->contexts[] = ['granted', $context];

        return $this->granted;
    }

    public function isLockedOutFor(MaintenanceAccessContext $context): bool
    {
        $this->contexts[] = ['locked', $context];

        return $this->locked;
    }

    public function authenticateFor(MaintenanceAccessContext $context, string $password): bool
    {
        $this->contexts[] = ['auth', $context];

        return $this->authResult;
    }

    public function revokeFor(MaintenanceAccessContext $context): void
    {
        $this->contexts[] = ['revoke', $context];
    }
}
