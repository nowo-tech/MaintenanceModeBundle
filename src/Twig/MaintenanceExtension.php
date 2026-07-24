<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Twig;

use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class MaintenanceExtension extends AbstractExtension
{
    public function __construct(
        private readonly MaintenanceManager $manager,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_maintenance_is_enabled', $this->isEffectivelyEnabled(...)),
            new TwigFunction('nowo_maintenance_state', $this->getState(...)),
        ];
    }

    public function isEffectivelyEnabled(): bool
    {
        return $this->manager->isEffectivelyEnabled();
    }

    public function getState(): MaintenanceState
    {
        return $this->manager->getState();
    }
}
