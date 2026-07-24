<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\DependencyInjection;

use Nowo\MaintenanceModeBundle\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testDefaults(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[]]);

        self::assertTrue($config['enabled']);
        self::assertSame('/_maintenance', $config['panel']['path_prefix']);
        self::assertTrue($config['panel']['enabled']);
        self::assertSame(503, $config['status_code']);
        self::assertTrue($config['security']['password_protection']);
        self::assertNull($config['security']['password_hash']);
        self::assertSame([], $config['exclusions']['paths']);
        self::assertStringContainsString('var/maintenance/state.json', $config['storage']['state_file']);
        self::assertSame('@NowoMaintenanceModeBundle/maintenance/page.html.twig', $config['templates']['page']);
    }

    public function testCustomExclusionsAndPanelPrefix(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'panel'      => ['path_prefix' => '/admin/maintenance'],
            'exclusions' => [
                'paths'    => ['/health'],
                'routes'   => ['app_health'],
                'patterns' => ['/_profiler*'],
            ],
            'security' => [
                'password_hash' => '$2y$10$example',
            ],
        ]]);

        self::assertSame('/admin/maintenance', $config['panel']['path_prefix']);
        self::assertSame(['/health'], $config['exclusions']['paths']);
        self::assertSame(['app_health'], $config['exclusions']['routes']);
        self::assertSame(['/_profiler*'], $config['exclusions']['patterns']);
        self::assertSame('$2y$10$example', $config['security']['password_hash']);
    }
}
