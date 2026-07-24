<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\DependencyInjection;

use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Nowo\MaintenanceModeBundle\Controller\MaintenancePreviewController;
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
use function is_bool;
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
        $container->setParameter('nowo.maintenance_mode.preview.enabled', $this->resolvePreviewEnabled($container, $config['preview']['enabled'] ?? null));
        $container->setParameter('nowo.maintenance_mode.preview.path', $config['preview']['path']);
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
        $this->configurePreview($container, $config);
    }

    public function getAlias(): string
    {
        return Configuration::ALIAS;
    }

    private function resolvePreviewEnabled(ContainerBuilder $container, mixed $configured): bool
    {
        if (is_bool($configured)) {
            return $configured;
        }

        return $container->hasParameter('kernel.debug') && (bool) $container->getParameter('kernel.debug');
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
        $paths       = array_values($exclusions['paths'] ?? []);
        $prefixes    = array_values($exclusions['path_prefixes'] ?? []);
        $panelPrefix = is_string($config['panel']['path_prefix'] ?? null) ? $config['panel']['path_prefix'] : '/_maintenance';
        if ($panelPrefix !== '' && !in_array($panelPrefix, $prefixes, true)) {
            $prefixes[] = $panelPrefix;
        }

        $previewPath = is_string($config['preview']['path'] ?? null) ? $config['preview']['path'] : '/_maintenance_preview';
        if ($previewPath !== '' && !in_array($previewPath, $paths, true)) {
            $paths[] = $previewPath;
        }

        $container->getDefinition(MaintenanceExclusionMatcher::class)
            ->setArgument('$paths', $paths)
            ->setArgument('$pathPrefixes', $prefixes)
            ->setArgument('$routes', array_values($exclusions['routes'] ?? []))
            ->setArgument('$patterns', array_values($exclusions['patterns'] ?? []))
            ->setArgument('$ips', array_values($exclusions['ips'] ?? []));
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
            ->setArgument('$defaultMessage', $config['default_message'])
            ->setArgument('$eventDispatcher', new Reference('event_dispatcher', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureSubscriber(ContainerBuilder $container, array $config): void
    {
        $security = $config['security'];
        $priority = (int) $config['subscriber_priority'];

        $definition = $container->getDefinition(MaintenanceRequestSubscriber::class);
        $definition
            ->setArgument('$enabled', (bool) $config['enabled'])
            ->setArgument('$manager', new Reference(MaintenanceManager::class))
            ->setArgument('$exclusionMatcher', new Reference(MaintenanceExclusionMatcher::class))
            ->setArgument('$twig', new Reference('twig', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
            ->setArgument('$template', $config['templates']['page'])
            ->setArgument('$statusCode', (int) $config['status_code'])
            ->setArgument('$retryAfter', (int) $config['retry_after'])
            ->setArgument('$panelPathPrefix', $config['panel']['path_prefix'])
            ->setArgument('$bypassToken', is_string($security['bypass_token'] ?? null) ? $security['bypass_token'] : null)
            ->setArgument('$bypassQueryParameter', (string) ($security['bypass_query_parameter'] ?? 'maintenance_bypass'))
            ->setArgument('$bypassCookieName', (string) ($security['bypass_cookie_name'] ?? 'nowo_maintenance_bypass'))
            ->setArgument('$bypassSetCookie', (bool) ($security['bypass_set_cookie'] ?? true));

        $definition->clearTags();
        $definition->addTag('kernel.event_listener', [
            'event'    => 'kernel.request',
            'method'   => 'onKernelRequest',
            'priority' => $priority,
        ]);
        $definition->addTag('kernel.event_listener', [
            'event'  => 'kernel.response',
            'method' => 'onKernelResponse',
        ]);
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

    /**
     * @param array<string, mixed> $config
     */
    private function configurePreview(ContainerBuilder $container, array $config): void
    {
        if (!$container->hasDefinition(MaintenancePreviewController::class)) {
            return; // @codeCoverageIgnore
        }

        $enabled = (bool) $container->getParameter('nowo.maintenance_mode.preview.enabled');

        $container->getDefinition(MaintenancePreviewController::class)
            ->setArgument('$enabled', $enabled)
            ->setArgument('$manager', new Reference(MaintenanceManager::class))
            ->setArgument('$twig', new Reference('twig'))
            ->setArgument('$template', $config['templates']['page'])
            ->setArgument('$defaultMessage', $config['default_message'])
            ->setArgument('$statusCode', (int) $config['status_code'])
            ->setArgument('$retryAfter', (int) $config['retry_after'])
            ->setPublic(true);
    }
}
