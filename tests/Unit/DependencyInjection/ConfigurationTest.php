<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\DependencyInjection;

use Nowo\MaintenanceModeBundle\DependencyInjection\Configuration;
use Nowo\MaintenanceModeBundle\Twig\PanelChrome;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
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
        self::assertSame(['ROLE_ADMIN'], $config['security']['access_roles']);
        self::assertNull($config['security']['access_checker']);
        self::assertFalse($config['security']['allow_unauthenticated']);
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
        self::assertTrue($config['web_ui']['enabled']);
        self::assertSame('@NowoMaintenanceModeBundle/panel/layout.html.twig', $config['web_ui']['layout_template']);
        self::assertSame('custom', $config['web_ui']['css_framework']);
        self::assertSame('none', $config['web_ui']['icon_set']);
        self::assertTrue($config['web_ui']['show_page_header']);
        self::assertTrue($config['web_ui']['document_title']);
        self::assertFalse($config['web_ui']['test_ids']);
        self::assertSame(PanelChrome::DEFAULT_CLASSES, $config['web_ui']['classes']);
        self::assertSame(PanelChrome::DEFAULT_PARTIALS, $config['web_ui']['partials']);
    }

    public function testAdminChromeOverrides(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'web_ui' => [
                'show_page_header' => false,
                'document_title'   => false,
                'test_ids'         => true,
                'classes'          => [
                    'card'       => 'panel mb-6',
                    'table_cell' => 'px-2 py-3',
                    'status_on'  => null,
                ],
                'partials' => [
                    'nav'        => '@NowoMaintenanceModeBundle/panel/_nav_tabs.html.twig',
                    'pagination' => 'kit/_pagination.html.twig',
                ],
            ],
        ]]);

        self::assertFalse($config['web_ui']['show_page_header']);
        self::assertFalse($config['web_ui']['document_title']);
        self::assertTrue($config['web_ui']['test_ids']);
        self::assertSame('panel mb-6', $config['web_ui']['classes']['card']);
        self::assertSame('px-2 py-3', $config['web_ui']['classes']['table_cell']);
        self::assertSame('', $config['web_ui']['classes']['status_on']);
        self::assertSame('nowo-ui-table', $config['web_ui']['classes']['table']);
        self::assertSame('@NowoMaintenanceModeBundle/panel/_nav_tabs.html.twig', $config['web_ui']['partials']['nav']);
        self::assertSame('kit/_pagination.html.twig', $config['web_ui']['partials']['pagination']);
        self::assertSame(PanelChrome::DEFAULT_PARTIALS['form_fields'], $config['web_ui']['partials']['form_fields']);
    }

    public function testAdminChromeRejectsUnknownClassKey(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'web_ui' => ['classes' => ['not_a_hook' => 'x']],
        ]]);
    }

    public function testAdminChromeRejectsEmptyNavPartial(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'web_ui' => ['partials' => ['nav' => '']],
        ]]);
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
