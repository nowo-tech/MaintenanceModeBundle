<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Panel;

use Nowo\MaintenanceModeBundle\Twig\PanelChrome;
use PHPUnit\Framework\TestCase;

use function array_keys;

final class PanelChromeBridgeTest extends TestCase
{
    public function testEveryClassKeyHasAMapping(): void
    {
        self::assertSame(PanelChrome::CLASS_KEYS, array_keys(PanelChrome::CLASS_SLOTS));
        self::assertSame(PanelChrome::CLASS_KEYS, array_keys(PanelChrome::DEFAULT_CLASSES));
    }

    public function testBridgeMapsHostValuesToSlotsWithoutFrameworkDefaults(): void
    {
        self::assertSame(['classes' => [
            'card'          => ['value' => 'panel mb-6', 'framework_defaults' => false],
            'table_cell'    => ['value' => 'px-2 py-3', 'framework_defaults' => false],
            'badge_warning' => ['value' => 'pill pill--on', 'framework_defaults' => false],
            'flash_error'   => ['value' => 'alert alert-danger', 'framework_defaults' => false],
        ]], PanelChrome::themeBridge([
            'card'        => ' panel mb-6 ',
            'table_cell'  => 'px-2 py-3',
            'status_on'   => 'pill pill--on',
            'login_error' => 'alert alert-danger',
            'status_line' => 'status-line',          // no slot
            'table'       => 'nowo-ui-table',        // 1.x default
            'empty'       => '',                     // 1.x "no class attribute"
            'page_title'  => null,
            7             => 'x',
        ]));
        self::assertSame([], PanelChrome::themeBridge([]));
    }
}
