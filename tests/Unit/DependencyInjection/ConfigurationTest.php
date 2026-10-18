<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\DependencyInjection;

use Nowo\MaintenanceModeBundle\DependencyInjection\Configuration;
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
        self::assertSame('/_maintenance', $config['web_ui']['path_prefix']);
        self::assertSame([], $config['panel']);
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
        self::assertSame(['page' => '@NowoMaintenanceModeBundle/maintenance/page.html.twig'], $config['templates']);
        self::assertNull($config['web_ui']['layout_template']);
        self::assertSame([], $config['web_ui']['templates']);
        self::assertSame(100, $config['web_ui']['history_limit']);
        self::assertSame(25, $config['web_ui']['history_per_page']);
        self::assertArrayNotHasKey('css_framework', $config['web_ui']);
        self::assertArrayNotHasKey('icon_set', $config['web_ui']);
        self::assertArrayNotHasKey('show_page_header', $config['web_ui']);
        self::assertSame([], $config['web_ui']['classes']);
        self::assertSame([], $config['web_ui']['partials']);
    }

    public function testDeprecatedChromeKeysAreKeptAsGiven(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'web_ui' => [
                'show_page_header' => false,
                'document_title'   => false,
                'test_ids'         => true,
                'classes'          => ['card' => 'panel mb-6', 'status_on' => null],
                'partials'         => ['nav' => '@NowoMaintenanceModeBundle/panel/_nav_tabs.html.twig', 'pagination' => null],
            ],
        ]]);

        self::assertFalse($config['web_ui']['show_page_header']);
        self::assertFalse($config['web_ui']['document_title']);
        self::assertTrue($config['web_ui']['test_ids']);
        self::assertSame(['card' => 'panel mb-6', 'status_on' => null], $config['web_ui']['classes']);
        self::assertSame(['nav' => '@NowoMaintenanceModeBundle/panel/_nav_tabs.html.twig', 'pagination' => null], $config['web_ui']['partials']);
    }

    public function testDeprecatedAliasesMapToCanonicalKeys(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'panel'     => ['enabled' => false, 'path_prefix' => '/ops/maintenance'],
            'templates' => [
                'panel_layout'  => 'admin/layout.html.twig',
                'panel_index'   => 'admin/maintenance.html.twig',
                'panel_login'   => '@NowoMaintenanceModeBundle/panel/login.html.twig',
                'panel_history' => '',
            ],
        ]]);

        self::assertFalse($config['web_ui']['enabled']);
        self::assertSame('/ops/maintenance', $config['web_ui']['path_prefix']);
        self::assertSame('admin/layout.html.twig', $config['web_ui']['layout_template']);
        self::assertSame(['index' => 'admin/maintenance.html.twig'], $config['web_ui']['templates'], '1.x defaults and empty values are no override.');
    }

    public function testCanonicalKeysWinOverDeprecatedAliases(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'panel'     => ['enabled' => false, 'path_prefix' => '/old'],
            'templates' => ['panel_layout' => 'old.html.twig', 'panel_index' => 'old_index.html.twig'],
            'web_ui'    => ['enabled' => true, 'path_prefix' => '/new', 'layout_template' => 'new.html.twig', 'templates' => ['index' => 'new_index.html.twig']],
        ]]);

        self::assertTrue($config['web_ui']['enabled']);
        self::assertSame('/new', $config['web_ui']['path_prefix']);
        self::assertSame('new.html.twig', $config['web_ui']['layout_template']);
        self::assertSame(['index' => 'new_index.html.twig'], $config['web_ui']['templates']);
    }

    public function testLegacyDefaultLayoutMapsToNull(): void
    {
        foreach ([['web_ui' => ['layout_template' => Configuration::LEGACY_LAYOUT_TEMPLATE]], ['templates' => ['panel_layout' => Configuration::LEGACY_LAYOUT_TEMPLATE]]] as $input) {
            $config = (new Processor())->processConfiguration(new Configuration(), [$input]);
            self::assertNull($config['web_ui']['layout_template']);
        }
    }

    public function testUnknownPageTemplateIsRejected(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unknown nowo_maintenance_mode.web_ui.templates page(s): settings');

        (new Processor())->processConfiguration(new Configuration(), [[
            'web_ui' => ['templates' => ['settings' => 'x.html.twig']],
        ]]);
    }

    public function testAdminChromeRejectsUnknownClassKey(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'web_ui' => ['classes' => ['not_a_hook' => 'x']],
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

        self::assertSame('/admin/maintenance', $config['web_ui']['path_prefix']);
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
