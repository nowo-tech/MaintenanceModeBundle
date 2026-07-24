<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Integration\DependencyInjection;

use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Nowo\MaintenanceModeBundle\DependencyInjection\MaintenanceModeExtension;
use Nowo\MaintenanceModeBundle\EventSubscriber\MaintenanceRequestSubscriber;
use Nowo\MaintenanceModeBundle\Exclusion\MaintenanceExclusionMatcher;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Security\PasswordMaintenanceAccessGate;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Storage\FilesystemMaintenanceStateStorage;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;
use PHPUnit\Framework\TestCase;
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

        $subscriberTags = $container->getDefinition(MaintenanceRequestSubscriber::class)->getTag('kernel.event_subscriber');
        self::assertSame(64, $subscriberTags[0]['priority']);

        $stateDef = $container->getDefinition(FilesystemMaintenanceStateStorage::class);
        self::assertNotEmpty($stateDef->getArgument('$filePath'));
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
    }

    public function testLoadRemovesPanelControllerWhenDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());

        $extension = new MaintenanceModeExtension();
        $extension->load([['panel' => ['enabled' => false]]], $container);

        self::assertFalse($container->hasDefinition(MaintenancePanelController::class));
    }

    public function testGetAlias(): void
    {
        self::assertSame('nowo_maintenance_mode', (new MaintenanceModeExtension())->getAlias());
    }
}
