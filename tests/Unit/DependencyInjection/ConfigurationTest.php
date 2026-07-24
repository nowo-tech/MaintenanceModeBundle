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
        self::assertStringContainsString('gentle improvements', $config['default_message']);
        self::assertSame('/_maintenance', $config['panel']['path_prefix']);
        self::assertTrue($config['panel']['enabled']);
        self::assertSame(503, $config['status_code']);
        self::assertSame(31, $config['subscriber_priority']);
        self::assertTrue($config['security']['password_protection']);
        self::assertNull($config['security']['password_hash']);
        self::assertNull($config['security']['bypass_token']);
        self::assertSame('maintenance_bypass', $config['security']['bypass_query_parameter']);
        self::assertSame('nowo_maintenance_bypass', $config['security']['bypass_cookie_name']);
        self::assertTrue($config['security']['bypass_set_cookie']);
        self::assertSame([], $config['exclusions']['paths']);
        self::assertSame([], $config['exclusions']['ips']);
        self::assertNull($config['preview']['enabled']);
        self::assertSame('/_maintenance_preview', $config['preview']['path']);
        self::assertStringContainsString('var/maintenance/state.json', $config['storage']['state_file']);
        self::assertSame('@NowoMaintenanceModeBundle/maintenance/page.html.twig', $config['templates']['page']);
    }

    public function testCustomExclusionsAndPanelPrefix(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'panel'      => ['path_prefix' => '/admin/maintenance'],
            'preview'    => ['enabled' => true, 'path' => '/_mm_preview'],
            'exclusions' => [
                'paths'    => ['/health'],
                'routes'   => ['app_health'],
                'patterns' => ['/_profiler*'],
                'ips'      => ['127.0.0.1'],
            ],
            'security' => [
                'password_hash' => '$2y$10$example',
                'bypass_token'  => 'secret',
            ],
        ]]);

        self::assertSame('/admin/maintenance', $config['panel']['path_prefix']);
        self::assertTrue($config['preview']['enabled']);
        self::assertSame('/_mm_preview', $config['preview']['path']);
        self::assertSame(['/health'], $config['exclusions']['paths']);
        self::assertSame(['app_health'], $config['exclusions']['routes']);
        self::assertSame(['/_profiler*'], $config['exclusions']['patterns']);
        self::assertSame(['127.0.0.1'], $config['exclusions']['ips']);
        self::assertSame('$2y$10$example', $config['security']['password_hash']);
        self::assertSame('secret', $config['security']['bypass_token']);
    }
}
