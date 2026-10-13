<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Twig;

use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

use function array_replace;
use function is_array;

final class MaintenanceExtension extends AbstractExtension implements GlobalsInterface
{
    /**
     * @var array{show_page_header: bool, document_title: bool, test_ids: bool, classes: array<string, string>, partials: array<string, string|null>}
     */
    private readonly array $chrome;

    /**
     * @param array<string, mixed> $chrome Admin chrome hooks (see PanelChrome::DEFAULTS); missing keys fall back to defaults
     */
    public function __construct(
        private readonly MaintenanceManager $manager,
        private readonly string $layoutTemplate = '@NowoMaintenanceModeBundle/panel/layout.html.twig',
        private readonly string $cssFramework = 'custom',
        private readonly string $iconSet = 'none',
        array $chrome = PanelChrome::DEFAULTS,
    ) {
        $classes  = is_array($chrome['classes'] ?? null) ? $chrome['classes'] : [];
        $partials = is_array($chrome['partials'] ?? null) ? $chrome['partials'] : [];

        /** @var array<string, string> $mergedClasses */
        $mergedClasses = array_replace(PanelChrome::DEFAULT_CLASSES, $classes);
        /** @var array<string, string|null> $mergedPartials */
        $mergedPartials = array_replace(PanelChrome::DEFAULT_PARTIALS, $partials);

        $this->chrome = [
            'show_page_header' => (bool) ($chrome['show_page_header'] ?? true),
            'document_title'   => (bool) ($chrome['document_title'] ?? true),
            'test_ids'         => (bool) ($chrome['test_ids'] ?? false),
            'classes'          => $mergedClasses,
            'partials'         => $mergedPartials,
        ];
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
            'nowo_maintenance_mode_chrome'          => $this->chrome,
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
