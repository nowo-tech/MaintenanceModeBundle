<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Twig;

use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryHistoryStorage;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryStateStorage;
use Nowo\MaintenanceModeBundle\Twig\MaintenanceExtension;
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
        self::assertSame('@NowoMaintenanceModeBundle/panel/layout.html.twig', $globals['nowo_maintenance_mode_layout_template']);
        self::assertSame('custom', $globals['nowo_maintenance_mode_css_framework']);
        self::assertSame('none', $globals['nowo_maintenance_mode_icon_set']);
    }
}
