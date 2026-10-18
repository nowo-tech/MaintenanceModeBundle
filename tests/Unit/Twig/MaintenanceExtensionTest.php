<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Twig;

use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryHistoryStorage;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryStateStorage;
use Nowo\MaintenanceModeBundle\Twig\MaintenanceExtension;
use Nowo\MaintenanceModeBundle\Twig\PanelChrome;
use PHPUnit\Framework\TestCase;

final class MaintenanceExtensionTest extends TestCase
{
    public function testFunctionsExposeManagerState(): void
    {
        $stateStorage        = new InMemoryStateStorage();
        $stateStorage->state = (new MaintenanceState())->withEnabled(true)->withMessage('Down');
        $extension           = new MaintenanceExtension(
            new MaintenanceManager($stateStorage, new InMemoryHistoryStorage()),
        );

        self::assertCount(2, $extension->getFunctions());
        self::assertTrue($extension->isEffectivelyEnabled());
        self::assertSame('Down', $extension->getState()->getMessage());
        $globals = $extension->getGlobals();
        self::assertNull($globals['nowo_maintenance_mode_layout_template']);
        self::assertSame('custom', $globals['nowo_maintenance_mode_css_framework']);
        self::assertSame('none', $globals['nowo_maintenance_mode_icon_set']);
        self::assertSame(PanelChrome::DEFAULTS, $globals['nowo_maintenance_mode_chrome']);
    }

    public function testChromeMergesPartialOverridesWithDefaults(): void
    {
        $extension = new MaintenanceExtension(
            new MaintenanceManager(new InMemoryStateStorage(), new InMemoryHistoryStorage()),
            'base.html.twig',
            'tailwind',
            'none',
            ['test_ids' => true, 'classes' => ['card' => 'panel'], 'partials' => ['pagination' => 'p.html.twig']],
        );

        /** @var array{show_page_header: bool, document_title: bool, test_ids: bool, classes: array<string, string>, partials: array<string, string|null>} $chrome */
        $chrome = $extension->getGlobals()['nowo_maintenance_mode_chrome'];
        self::assertTrue($chrome['show_page_header']);
        self::assertTrue($chrome['document_title']);
        self::assertTrue($chrome['test_ids']);
        self::assertSame('panel', $chrome['classes']['card']);
        self::assertSame('nowo-ui-table', $chrome['classes']['table']);
        self::assertSame('p.html.twig', $chrome['partials']['pagination']);
        self::assertSame(PanelChrome::DEFAULT_PARTIALS['nav'], $chrome['partials']['nav']);
    }

    public function testChromeIgnoresNonArrayMaps(): void
    {
        $extension = new MaintenanceExtension(
            new MaintenanceManager(new InMemoryStateStorage(), new InMemoryHistoryStorage()),
            chrome: ['classes' => 'invalid', 'partials' => null, 'show_page_header' => false],
        );

        self::assertSame(
            ['show_page_header' => false] + PanelChrome::DEFAULTS,
            $extension->getGlobals()['nowo_maintenance_mode_chrome'],
        );
    }
}
