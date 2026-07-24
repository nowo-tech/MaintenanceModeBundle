<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\DependencyInjection;

use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Nowo\MaintenanceModeBundle\EventSubscriber\MaintenanceRequestSubscriber;
use Nowo\MaintenanceModeBundle\Exclusion\MaintenanceExclusionMatcher;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Security\PasswordMaintenanceAccessGate;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Storage\FilesystemMaintenanceHistoryStorage;
use Nowo\MaintenanceModeBundle\Storage\FilesystemMaintenanceStateStorage;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function array_values;
use function in_array;
use function is_string;

final class MaintenanceModeExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $container->setParameter('nowo.maintenance_mode.enabled', $config['enabled']);
        $container->setParameter('nowo.maintenance_mode.default_message', $config['default_message']);
        $container->setParameter('nowo.maintenance_mode.status_code', $config['status_code']);
        $container->setParameter('nowo.maintenance_mode.retry_after', $config['retry_after']);
        $container->setParameter('nowo.maintenance_mode.subscriber_priority', $config['subscriber_priority']);
        $container->setParameter('nowo.maintenance_mode.panel.enabled', $config['panel']['enabled']);
        $container->setParameter('nowo.maintenance_mode.panel.path_prefix', $config['panel']['path_prefix']);
        $container->setParameter('nowo.maintenance_mode.exclusions', $config['exclusions']);
        $container->setParameter('nowo.maintenance_mode.security', $config['security']);
        $container->setParameter('nowo.maintenance_mode.storage', $config['storage']);
        $container->setParameter('nowo.maintenance_mode.storage.state_file', $config['storage']['state_file']);
        $container->setParameter('nowo.maintenance_mode.storage.history_file', $config['storage']['history_file']);
        $container->setParameter('nowo.maintenance_mode.templates', $config['templates']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $this->configureStorage($container, $config['storage']);
        $this->configureExclusions($container, $config);
        $this->configureAccessGate($container, $config['security']);
        $this->configureManager($container, $config);
        $this->configureSubscriber($container, $config);
        $this->configurePanel($container, $config);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    /**
     * @param array<string, mixed> $storage
     */
    private function configureStorage(ContainerBuilder $container, array $storage): void
    {
        $stateFile   = is_string($storage['state_file'] ?? null) ? $storage['state_file'] : '%kernel.project_dir%/var/maintenance/state.json';
        $historyFile = is_string($storage['history_file'] ?? null) ? $storage['history_file'] : '%kernel.project_dir%/var/maintenance/history.jsonl';

        $container->getDefinition(FilesystemMaintenanceStateStorage::class)
            ->setArgument('$filePath', $stateFile);

        $container->getDefinition(FilesystemMaintenanceHistoryStorage::class)
            ->setArgument('$filePath', $historyFile);

        $stateOverride = $storage['state_storage'] ?? null;
        if (is_string($stateOverride) && $stateOverride !== '') {
            $container->setAlias(MaintenanceStateStorageInterface::class, $stateOverride)->setPublic(false);
        } else {
            $container->setAlias(MaintenanceStateStorageInterface::class, FilesystemMaintenanceStateStorage::class)->setPublic(false);
        }

        $historyOverride = $storage['history_storage'] ?? null;
        if (is_string($historyOverride) && $historyOverride !== '') {
            $container->setAlias(MaintenanceHistoryStorageInterface::class, $historyOverride)->setPublic(false);
        } else {
            $container->setAlias(MaintenanceHistoryStorageInterface::class, FilesystemMaintenanceHistoryStorage::class)->setPublic(false);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureExclusions(ContainerBuilder $container, array $config): void
    {
        /** @var array{paths?: list<string>, path_prefixes?: list<string>, routes?: list<string>, patterns?: list<string>} $exclusions */
        $exclusions  = $config['exclusions'];
        $prefixes    = array_values($exclusions['path_prefixes'] ?? []);
        $panelPrefix = is_string($config['panel']['path_prefix'] ?? null) ? $config['panel']['path_prefix'] : '/_maintenance';
        if ($panelPrefix !== '' && !in_array($panelPrefix, $prefixes, true)) {
            $prefixes[] = $panelPrefix;
        }

        $container->getDefinition(MaintenanceExclusionMatcher::class)
            ->setArgument('$paths', array_values($exclusions['paths'] ?? []))
            ->setArgument('$pathPrefixes', $prefixes)
            ->setArgument('$routes', array_values($exclusions['routes'] ?? []))
            ->setArgument('$patterns', array_values($exclusions['patterns'] ?? []));
    }

    /**
     * @param array<string, mixed> $security
     */
    private function configureAccessGate(ContainerBuilder $container, array $security): void
    {
        $custom = $security['access_gate'] ?? null;
        if (is_string($custom) && $custom !== '') {
            $container->setAlias(MaintenanceAccessGateInterface::class, $custom)->setPublic(false);

            return;
        }

        $hash = $security['password_hash'] ?? null;
        $container->getDefinition(PasswordMaintenanceAccessGate::class)
            ->setArgument('$passwordHash', is_string($hash) ? $hash : null)
            ->setArgument('$enabled', (bool) ($security['password_protection'] ?? true));

        $container->setAlias(MaintenanceAccessGateInterface::class, PasswordMaintenanceAccessGate::class)->setPublic(false);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureManager(ContainerBuilder $container, array $config): void
    {
        $container->getDefinition(MaintenanceManager::class)
            ->setArgument('$stateStorage', new Reference(MaintenanceStateStorageInterface::class))
            ->setArgument('$historyStorage', new Reference(MaintenanceHistoryStorageInterface::class))
            ->setArgument('$defaultMessage', $config['default_message']);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureSubscriber(ContainerBuilder $container, array $config): void
    {
        $definition = $container->getDefinition(MaintenanceRequestSubscriber::class);
        $definition
            ->setArgument('$enabled', (bool) $config['enabled'])
            ->setArgument('$manager', new Reference(MaintenanceManager::class))
            ->setArgument('$exclusionMatcher', new Reference(MaintenanceExclusionMatcher::class))
            ->setArgument('$twig', new Reference('twig', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
            ->setArgument('$template', $config['templates']['page'])
            ->setArgument('$statusCode', (int) $config['status_code'])
            ->setArgument('$retryAfter', (int) $config['retry_after'])
            ->setArgument('$panelPathPrefix', $config['panel']['path_prefix']);

        $priority = (int) $config['subscriber_priority'];
        $definition->clearTags();
        $definition->addTag('kernel.event_subscriber', ['priority' => $priority]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configurePanel(ContainerBuilder $container, array $config): void
    {
        // Defensive: services.yaml always registers the controller when the extension loads.
        if (!$container->hasDefinition(MaintenancePanelController::class)) {
            return; // @codeCoverageIgnore
        }

        if (!(bool) $config['panel']['enabled']) {
            $container->removeDefinition(MaintenancePanelController::class);

            return;
        }

        $container->getDefinition(MaintenancePanelController::class)
            ->setArgument('$manager', new Reference(MaintenanceManager::class))
            ->setArgument('$accessGate', new Reference(MaintenanceAccessGateInterface::class))
            ->setArgument('$templates', $config['templates'])
            ->setArgument('$pathPrefix', $config['panel']['path_prefix'])
            ->setArgument('$csrfTokenManager', new Reference(CsrfTokenManagerInterface::class, ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
            ->setPublic(true);
    }
}
