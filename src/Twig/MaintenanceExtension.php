<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Twig;

use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class MaintenanceExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly MaintenanceManager $manager,
        private readonly string $layoutTemplate = '@NowoMaintenanceModeBundle/panel/layout.html.twig',
        private readonly string $cssFramework = 'custom',
        private readonly string $iconSet = 'none',
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('nowo_maintenance_is_enabled', $this->isEffectivelyEnabled(...)),
            new TwigFunction('nowo_maintenance_state', $this->getState(...)),
        ];
    }

    public function getGlobals(): array
    {
        return [
            'nowo_maintenance_mode_layout_template' => $this->layoutTemplate,
            'nowo_maintenance_mode_css_framework'   => $this->cssFramework,
            'nowo_maintenance_mode_icon_set'        => $this->iconSet,
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
