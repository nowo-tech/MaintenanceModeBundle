<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Support\HostPorts;

use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessContext;
use Nowo\MaintenanceModeBundle\Security\MaintenancePanelAccessGateInterface;
use Symfony\Component\DependencyInjection\Attribute\AsDecorator;
use Symfony\Component\DependencyInjection\Attribute\AutowireDecorated;

/**
 * Host decorator of the framework-free gate only (`#[AsDecorator]` on the new interface): narrows whatever the inner
 * gate grants to the ops address. Used to prove the panel goes through the decorated new port (audit MK-S-M1).
 */
#[AsDecorator(MaintenancePanelAccessGateInterface::class)]
final readonly class OpsOnlyDecoratingGate implements MaintenancePanelAccessGateInterface
{
    public function __construct(
        #[AutowireDecorated]
        private MaintenancePanelAccessGateInterface $inner,
    ) {
    }

    public function isPasswordRequired(): bool
    {
        return true;
    }

    public function isGrantedFor(MaintenanceAccessContext $context): bool
    {
        return $context->clientIp === IpAllowListContextGate::OPS_IP && ($this->inner->isGrantedFor($context) || !$this->inner->isPasswordRequired());
    }

    public function isLockedOutFor(MaintenanceAccessContext $context): bool
    {
        return $this->inner->isLockedOutFor($context);
    }

    public function authenticateFor(MaintenanceAccessContext $context, string $password): bool
    {
        return false;
    }

    public function revokeFor(MaintenanceAccessContext $context): void
    {
        $this->inner->revokeFor($context);
    }
}
