<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Integration\DependencyInjection;

use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Nowo\MaintenanceModeBundle\Controller\MaintenancePreviewController;
use Nowo\MaintenanceModeBundle\DependencyInjection\MaintenanceModeExtension;
use Nowo\MaintenanceModeBundle\EventSubscriber\MaintenanceRequestSubscriber;
use Nowo\MaintenanceModeBundle\Exclusion\MaintenanceExclusionMatcher;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Security\PasswordMaintenanceAccessGate;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Storage\FilesystemMaintenanceStateStorage;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;
use Nowo\MaintenanceModeBundle\Twig\MaintenanceExtension as TwigMaintenanceExtension;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

final class MaintenanceModeExtensionIntegrationTest extends TestCase
{
    public function testLoadRegistersCoreServices(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new MaintenanceModeExtension();
        $extension->load([[
            'enabled' => true,
            'panel'   => ['enabled' => false],
        ]], $container);

        self::assertTrue($container->hasDefinition(MaintenanceManager::class));
        self::assertTrue($container->hasDefinition(MaintenanceRequestSubscriber::class));
        self::assertTrue($container->hasDefinition(MaintenanceExclusionMatcher::class));
        self::assertTrue($container->hasAlias(MaintenanceStateStorageInterface::class));
        self::assertTrue($container->hasAlias(MaintenanceHistoryStorageInterface::class));
        self::assertTrue($container->hasAlias(MaintenanceAccessGateInterface::class));
        self::assertTrue($container->getParameter('nowo.maintenance_mode.enabled'));
        self::assertSame('/_maintenance', $container->getParameter('nowo.maintenance_mode.panel.path_prefix'));
    }

    public function testLoadWithPanelEnabledRegistersController(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new MaintenanceModeExtension();
        $extension->load([[
            'enabled' => true,
            'panel'   => ['enabled' => true],
        ]], $container);

        self::assertTrue($container->hasDefinition(MaintenancePanelController::class));
        self::assertTrue($container->getDefinition(MaintenancePanelController::class)->isPublic());
    }

    public function testLoadWithCustomStorageAndAccessGateOverrides(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setDefinition('app.custom_state_storage', new Definition('stdClass'));
        $container->setDefinition('app.custom_history_storage', new Definition('stdClass'));
        $container->setDefinition('app.custom_access_gate', new Definition('stdClass'));

        $extension = new MaintenanceModeExtension();
        $extension->load([[
            'panel'   => ['enabled' => false],
            'storage' => [
                'state_storage'   => 'app.custom_state_storage',
                'history_storage' => 'app.custom_history_storage',
            ],
            'security' => [
                'access_gate' => 'app.custom_access_gate',
            ],
        ]], $container);

        self::assertSame('app.custom_state_storage', (string) $container->getAlias(MaintenanceStateStorageInterface::class));
        self::assertSame('app.custom_history_storage', (string) $container->getAlias(MaintenanceHistoryStorageInterface::class));
        self::assertSame('app.custom_access_gate', (string) $container->getAlias(MaintenanceAccessGateInterface::class));
    }

    public function testLoadConfiguresPasswordGateAndSubscriberPriority(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new MaintenanceModeExtension();
        $extension->load([[
            'enabled'             => true,
            'subscriber_priority' => 64,
            'panel'               => ['enabled' => false],
            'security'            => [
                'password_hash'       => '$2y$10$example',
                'password_protection' => false,
            ],
        ]], $container);

        $gateDef = $container->getDefinition(PasswordMaintenanceAccessGate::class);
        self::assertSame('$2y$10$example', $gateDef->getArgument('$passwordHash'));
        self::assertFalse($gateDef->getArgument('$enabled'));

        $requestTags = $container->getDefinition(MaintenanceRequestSubscriber::class)->getTag('kernel.event_listener');
        self::assertSame('kernel.request', $requestTags[0]['event']);
        self::assertSame(64, $requestTags[0]['priority']);
        self::assertSame('onKernelRequest', $requestTags[0]['method']);
        self::assertSame('kernel.response', $requestTags[1]['event']);
        self::assertSame('onKernelResponse', $requestTags[1]['method']);

        $stateDef = $container->getDefinition(FilesystemMaintenanceStateStorage::class);
        self::assertNotEmpty($stateDef->getArgument('$filePath'));
    }

    public function testLoadConfiguresIpsAndBypassToken(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new MaintenanceModeExtension();
        $extension->load([[
            'panel'      => ['enabled' => false],
            'exclusions' => [
                'ips' => ['127.0.0.1', '10.0.0.0/8'],
            ],
            'security' => [
                'bypass_token'           => 'qa-secret',
                'bypass_query_parameter' => 'mm_bypass',
                'bypass_cookie_name'     => 'mm_cookie',
                'bypass_set_cookie'      => false,
            ],
        ]], $container);

        $matcherDef = $container->getDefinition(MaintenanceExclusionMatcher::class);
        self::assertSame(['127.0.0.1', '10.0.0.0/8'], $matcherDef->getArgument('$ips'));

        $subscriber = $container->getDefinition(MaintenanceRequestSubscriber::class);
        self::assertSame('qa-secret', $subscriber->getArgument('$bypassToken'));
        self::assertSame('mm_bypass', $subscriber->getArgument('$bypassQueryParameter'));
        self::assertSame('mm_cookie', $subscriber->getArgument('$bypassCookieName'));
        self::assertFalse($subscriber->getArgument('$bypassSetCookie'));
    }

    public function testLoadAddsPanelPrefixToExclusionMatcher(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new MaintenanceModeExtension();
        $extension->load([[
            'panel' => [
                'enabled'     => false,
                'path_prefix' => '/custom-panel',
            ],
            'exclusions' => [
                'path_prefixes' => [],
            ],
        ]], $container);

        $matcherDef = $container->getDefinition(MaintenanceExclusionMatcher::class);
        self::assertContains('/custom-panel', $matcherDef->getArgument('$pathPrefixes'));
        self::assertContains('/_maintenance_preview', $matcherDef->getArgument('$paths'));
    }

    public function testLoadEnablesPreviewFromKernelDebug(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', true);

        $extension = new MaintenanceModeExtension();
        $extension->load([['panel' => ['enabled' => false]]], $container);

        self::assertTrue($container->getParameter('nowo.maintenance_mode.preview.enabled'));
        self::assertSame('/_maintenance_preview', $container->getParameter('nowo.maintenance_mode.preview.path'));

        $preview = $container->getDefinition(MaintenancePreviewController::class);
        self::assertTrue($preview->getArgument('$enabled'));
    }

    public function testLoadCanDisablePreviewExplicitly(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->setParameter('kernel.debug', true);

        $extension = new MaintenanceModeExtension();
        $extension->load([[
            'panel'   => ['enabled' => false],
            'preview' => ['enabled' => false, 'path' => '/_custom_mm_preview'],
        ]], $container);

        self::assertFalse($container->getParameter('nowo.maintenance_mode.preview.enabled'));
        self::assertSame('/_custom_mm_preview', $container->getParameter('nowo.maintenance_mode.preview.path'));
        self::assertContains('/_custom_mm_preview', $container->getDefinition(MaintenanceExclusionMatcher::class)->getArgument('$paths'));
        self::assertFalse($container->getDefinition(MaintenancePreviewController::class)->getArgument('$enabled'));
    }

    public function testLoadRemovesPanelControllerWhenDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new MaintenanceModeExtension();
        $extension->load([['panel' => ['enabled' => false]]], $container);

        self::assertFalse($container->hasDefinition(MaintenancePanelController::class));
    }

    public function testLoadPreservesLegacyPanelLayoutWhenWebUiUsesDefault(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $legacy    = '@App/layouts/admin.html.twig';
        $extension = new MaintenanceModeExtension();
        $extension->load([[
            'panel'     => ['enabled' => false],
            'templates' => [
                'panel_layout' => $legacy,
            ],
        ]], $container);

        self::assertSame($legacy, $container->getParameter('nowo.maintenance_mode.web_ui.layout_template'));
        /** @var array<string, mixed> $templates */
        $templates = $container->getParameter('nowo.maintenance_mode.templates');
        self::assertSame($legacy, $templates['panel_layout']);
    }

    public function testLoadSyncsPanelLayoutFromWebUi(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $layout    = 'base.html.twig';
        $extension = new MaintenanceModeExtension();
        $extension->load([[
            'panel'  => ['enabled' => false],
            'web_ui' => [
                'layout_template' => $layout,
                'css_framework'   => 'bootstrap5',
                'icon_set'        => 'bootstrap-icons',
            ],
        ]], $container);

        self::assertSame($layout, $container->getParameter('nowo.maintenance_mode.web_ui.layout_template'));
        /** @var array<string, mixed> $templates */
        $templates = $container->getParameter('nowo.maintenance_mode.templates');
        self::assertSame($layout, $templates['panel_layout']);
        self::assertSame('bootstrap5', $container->getParameter('nowo.maintenance_mode.web_ui.css_framework'));
        self::assertSame('bootstrap-icons', $container->getParameter('nowo.maintenance_mode.web_ui.icon_set'));

        $twigDef = $container->getDefinition(TwigMaintenanceExtension::class);
        self::assertSame($layout, $twigDef->getArgument('$layoutTemplate'));
        self::assertSame('bootstrap5', $twigDef->getArgument('$cssFramework'));
        self::assertSame('bootstrap-icons', $twigDef->getArgument('$iconSet'));
    }

    public function testConfigureTwigExtensionNoopsWithoutDefinition(): void
    {
        $container = new ContainerBuilder();
        $extension = new MaintenanceModeExtension();
        $method    = new ReflectionMethod(MaintenanceModeExtension::class, 'configureTwigExtension');
        $method->setAccessible(true);
        $method->invoke($extension, $container, [
            'web_ui' => [
                'layout_template' => '@NowoMaintenanceModeBundle/panel/layout.html.twig',
                'css_framework'   => 'custom',
                'icon_set'        => 'none',
            ],
        ]);

        self::assertFalse($container->hasDefinition(TwigMaintenanceExtension::class));
    }

    public function testGetAlias(): void
    {
        self::assertSame('nowo_maintenance_mode', (new MaintenanceModeExtension())->getAlias());
    }
}
