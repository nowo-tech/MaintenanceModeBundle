<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\DependencyInjection;

use LogicException;
use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Nowo\MaintenanceModeBundle\Controller\MaintenancePreviewController;
use Nowo\MaintenanceModeBundle\EventSubscriber\MaintenanceRequestSubscriber;
use Nowo\MaintenanceModeBundle\Exclusion\MaintenanceExclusionMatcher;
use Nowo\MaintenanceModeBundle\Form\AbstractMaintenanceFormType;
use Nowo\MaintenanceModeBundle\NowoMaintenanceModeBundle;
use Nowo\MaintenanceModeBundle\Panel\PanelView;
use Nowo\MaintenanceModeBundle\Security\AllowAllMaintenanceModeAccessChecker;
use Nowo\MaintenanceModeBundle\Security\ConfigurableMaintenanceModeAccessChecker;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceModeAccessCheckerInterface;
use Nowo\MaintenanceModeBundle\Security\PasswordMaintenanceAccessGate;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Storage\FilesystemMaintenanceHistoryStorage;
use Nowo\MaintenanceModeBundle\Storage\FilesystemMaintenanceStateStorage;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;
use Nowo\MaintenanceModeBundle\Twig\MaintenanceExtension as TwigMaintenanceExtension;
use Nowo\MaintenanceModeBundle\Twig\PanelChrome;
use Psr\Clock\ClockInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use function array_key_exists;
use function array_values;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function rtrim;

final class MaintenanceModeExtension extends Extension implements PrependExtensionInterface
{
    /**
     * Seeds UiKit defaults from the deprecated web_ui keys when the host has not set nowo_ui_kit (REQ-UI-001-kit) and
     * bridges the deprecated web_ui.classes map into nowo_ui_kit.theme.bundles.NowoMaintenanceModeBundle (REQ-UI-003).
     */
    public function prepend(ContainerBuilder $container): void
    {
        $this->prependUiKitDefaults($container);
        $this->prependFormKitDefaults($container);
    }

    /**
     * Registers the `maintenance_mode` FormKit profile of the panel forms (REQ-TWIG-005 rule 11) unless the host
     * defines `nowo_form_kit.profiles.maintenance_mode` itself. The profile adds no classes and no convention label /
     * placeholder / help keys: the forms render exactly their explicit options until a host themes the profile.
     */
    private function prependFormKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_form_kit')) {
            return;
        }

        $configs = $container->getExtensionConfig('nowo_form_kit');
        foreach ($configs as $cfg) {
            $profiles = $cfg['profiles'] ?? null;
            if (is_array($profiles) && array_key_exists(AbstractMaintenanceFormType::FORM_KIT_PROFILE, $profiles)) {
                return;
            }
        }

        $seed = ['profiles' => [AbstractMaintenanceFormType::FORM_KIT_PROFILE => self::formKitProfile()]];
        if ($configs === []) {
            // No FormKit config in the host: keep FormKit's implicit `default` profile as the default one (a config
            // that only lists this profile would otherwise make it the default of every host form).
            $seed['default_profile']     = 'default';
            $seed['profiles']['default'] = ['alias' => 'default'];
        }

        $container->prependExtensionConfig('nowo_form_kit', $seed);
    }

    /**
     * Default `nowo_form_kit.profiles.maintenance_mode` (REQ-UI-003 "Form theme" row).
     *
     * @return array<string, mixed>
     */
    public static function formKitProfile(): array
    {
        return [
            'alias'              => AbstractMaintenanceFormType::FORM_KIT_PROFILE,
            'translation_domain' => NowoMaintenanceModeBundle::TRANSLATION_DOMAIN,
            'auto_placeholder'   => false,
            'auto_help'          => false,
        ];
    }

    /**
     * When UiKit is installed: seed nowo_ui_kit.css_framework / icon_set from the deprecated web_ui keys when the host
     * set them (and not the nowo_ui_kit ones), and bridge the deprecated web_ui.classes map into
     * nowo_ui_kit.theme.bundles.NowoMaintenanceModeBundle. Prepended: the host's own UiKit config wins.
     */
    private function prependUiKitDefaults(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('nowo_ui_kit')) {
            return;
        }

        $hostHasCssFramework = false;
        $hostHasIconSet      = false;
        foreach ($container->getExtensionConfig('nowo_ui_kit') as $cfg) {
            if (!is_array($cfg)) {
                continue;
            }
            if (array_key_exists('css_framework', $cfg)) {
                $hostHasCssFramework = true;
            }
            if (array_key_exists('icon_set', $cfg)) {
                $hostHasIconSet = true;
            }
        }

        $config   = $this->processConfiguration(new Configuration(), $container->getExtensionConfig(Configuration::ALIAS));
        $webUi    = is_array($config['web_ui'] ?? null) ? $config['web_ui'] : [];
        $defaults = [];

        $fw = $webUi['css_framework'] ?? null;
        if (!$hostHasCssFramework && is_string($fw)) {
            $defaults['css_framework'] = $fw === 'bootstrap' ? 'bootstrap5' : $fw;
        }
        $iconSet = $webUi['icon_set'] ?? null;
        if (!$hostHasIconSet && is_string($iconSet)) {
            $defaults['icon_set'] = $iconSet;
        }

        $bridge = PanelChrome::themeBridge(is_array($webUi['classes'] ?? null) ? $webUi['classes'] : []);
        if ($bridge !== []) {
            $defaults['theme'] = ['bundles' => [PanelView::BUNDLE => $bridge]];
        }

        if ($defaults !== []) {
            $container->prependExtensionConfig('nowo_ui_kit', $defaults);
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config        = $this->processConfiguration($configuration, $configs);

        $container->setParameter('nowo.maintenance_mode.enabled', $config['enabled']);
        $container->setParameter('nowo.maintenance_mode.default_message', $config['default_message']);
        $container->setParameter('nowo.maintenance_mode.status_code', $config['status_code']);
        $container->setParameter('nowo.maintenance_mode.retry_after', $config['retry_after']);
        $container->setParameter('nowo.maintenance_mode.subscriber_priority', $config['subscriber_priority']);
        $webUi = $config['web_ui'];
        /** @var array<string, string> $pageTemplates */
        $pageTemplates = $webUi['templates'];
        $pageTemplates += PanelView::TEMPLATES;
        $panelEnabled = (bool) $webUi['enabled'];
        $pathPrefix   = (string) $webUi['path_prefix'];

        $container->setParameter('nowo.maintenance_mode.panel.enabled', $panelEnabled);
        $container->setParameter('nowo.maintenance_mode.panel.path_prefix', $pathPrefix);
        $container->setParameter('nowo.maintenance_mode.preview.enabled', $this->resolvePreviewEnabled($container, $config['preview']['enabled'] ?? null));
        $container->setParameter('nowo.maintenance_mode.preview.path', $config['preview']['path']);
        $container->setParameter('nowo.maintenance_mode.exclusions', $config['exclusions']);
        $container->setParameter('nowo.maintenance_mode.security', $config['security']);
        $container->setParameter('nowo.maintenance_mode.security.allow_unauthenticated', $config['security']['allow_unauthenticated']);
        $container->setParameter('nowo.maintenance_mode.storage', $config['storage']);
        $container->setParameter('nowo.maintenance_mode.storage.state_file', $config['storage']['state_file']);
        $container->setParameter('nowo.maintenance_mode.storage.history_file', $config['storage']['history_file']);
        // 1.x shape (page + deprecated panel_* keys) resolved from the canonical keys.
        $container->setParameter('nowo.maintenance_mode.templates', [
            'page'          => $config['templates']['page'],
            'panel_layout'  => $webUi['layout_template'],
            'panel_index'   => $pageTemplates['index'],
            'panel_login'   => $pageTemplates['login'],
            'panel_history' => $pageTemplates['history'],
        ]);
        $container->setParameter('nowo.maintenance_mode.web_ui', $webUi);
        $container->setParameter('nowo.maintenance_mode.web_ui.enabled', $panelEnabled);
        $container->setParameter('nowo.maintenance_mode.web_ui.path_prefix', $pathPrefix);
        $container->setParameter('nowo.maintenance_mode.web_ui.layout_template', $webUi['layout_template']);
        $container->setParameter('nowo.maintenance_mode.web_ui.templates', $pageTemplates);
        $container->setParameter('nowo.maintenance_mode.web_ui.css_framework', $webUi['css_framework'] ?? null);
        $container->setParameter('nowo.maintenance_mode.web_ui.icon_set', $webUi['icon_set'] ?? null);

        if (
            $panelEnabled
            && !$config['security']['allow_unauthenticated']
            && !$this->isSecurityBundleAvailable($container)
        ) {
            throw new LogicException('NowoMaintenanceModeBundle panel requires symfony/security-bundle when security.allow_unauthenticated is false.');
        }

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        $this->configureStorage($container, $config['storage']);
        $this->configureExclusions($container, $config);
        $this->configureAccessGate($container, $config['security']);
        $this->registerAccessChecker($container, $config['security']);
        $this->configureManager($container, $config);
        $this->configureSubscriber($container, $config);
        $this->configurePanel($container, $config, $pathPrefix, $pageTemplates);
        $this->configurePreview($container, $config);
        $this->configureTwigExtension($container, $config);
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
        $panelPrefix = (string) $config['web_ui']['path_prefix'];
        // Segment-aware: exclude "/_maintenance" and "/_maintenance/…", never "/_maintenance-public".
        $panelBase = rtrim($panelPrefix, '/');
        if ($panelBase !== '') {
            if (!in_array($panelBase, $paths, true)) {
                $paths[] = $panelBase;
            }
            if (!in_array($panelBase . '/', $prefixes, true)) {
                $prefixes[] = $panelBase . '/';
            }
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
            ->setArgument('$enabled', (bool) ($security['password_protection'] ?? true))
            ->setArgument('$maxAttempts', (int) ($security['login_max_attempts'] ?? 5))
            ->setArgument('$lockoutSeconds', (int) ($security['login_lockout_seconds'] ?? 900))
            ->setArgument('$cache', new Reference('cache.app', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
            ->setArgument('$clock', new Reference(ClockInterface::class, ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
            ->setArgument('$logger', new Reference('logger', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE));

        $container->setAlias(MaintenanceAccessGateInterface::class, PasswordMaintenanceAccessGate::class)->setPublic(false);
    }

    /** @param array<string, mixed> $security */
    private function registerAccessChecker(ContainerBuilder $container, array $security): void
    {
        $accessCheckerId = $security['access_checker'] ?? null;
        if (is_string($accessCheckerId) && $accessCheckerId !== '') {
            $container->setAlias(MaintenanceModeAccessCheckerInterface::class, $accessCheckerId)->setPublic(false);

            return;
        }

        if ((bool) ($security['allow_unauthenticated'] ?? false)) {
            $accessCheckerId = 'nowo_maintenance_mode.access_checker.allow_all';
            $container->setDefinition($accessCheckerId, new Definition(AllowAllMaintenanceModeAccessChecker::class));
        } else {
            $accessCheckerId = 'nowo_maintenance_mode.access_checker.default';
            $container->setDefinition($accessCheckerId, (new Definition(ConfigurableMaintenanceModeAccessChecker::class))
                ->setAutowired(true)
                ->setArgument('$accessRoles', $security['access_roles']));
        }

        $container->setAlias(MaintenanceModeAccessCheckerInterface::class, $accessCheckerId)->setPublic(false);
    }

    /**
     * Prefer kernel.bundles: ContainerBuilder::hasExtension() can be false while SecurityBundle
     * is already registered (e.g. during early Flex cache:clear boots).
     */
    private function isSecurityBundleAvailable(ContainerBuilder $container): bool
    {
        if ($container->hasExtension('security')) {
            return true;
        }

        if (!$container->hasParameter('kernel.bundles')) {
            return false;
        }

        /** @var array<string, class-string> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        return isset($bundles['SecurityBundle']);
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
            ->setArgument('$eventDispatcher', new Reference('event_dispatcher', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
            ->setArgument('$clock', new Reference(ClockInterface::class, ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
            ->setArgument('$logger', new Reference('logger', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE));
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
            ->setArgument('$panelPathPrefix', (string) $config['web_ui']['path_prefix'])
            ->setArgument('$bypassToken', is_string($security['bypass_token'] ?? null) ? $security['bypass_token'] : null)
            ->setArgument('$bypassQueryParameter', (string) ($security['bypass_query_parameter'] ?? 'maintenance_bypass'))
            ->setArgument('$bypassCookieName', (string) ($security['bypass_cookie_name'] ?? 'nowo_maintenance_bypass'))
            ->setArgument('$bypassSetCookie', (bool) ($security['bypass_set_cookie'] ?? true))
            ->setArgument('$clock', new Reference(ClockInterface::class, ContainerBuilder::IGNORE_ON_INVALID_REFERENCE));

        // Tagged here instead of #[AsEventListener] because the kernel.request priority is configurable
        // (`subscriber_priority`), which an attribute cannot read (documented exception to REQ-SF-003).
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
     * @param array<string, string> $pageTemplates
     */
    private function configurePanel(ContainerBuilder $container, array $config, string $pathPrefix, array $pageTemplates): void
    {
        // Defensive: services.yaml always registers the controller when the extension loads.
        if (!$container->hasDefinition(MaintenancePanelController::class)) {
            return; // @codeCoverageIgnore
        }

        if (!(bool) $config['web_ui']['enabled']) {
            $container->removeDefinition(MaintenancePanelController::class);
            $container->removeDefinition(PanelView::class);

            return;
        }

        $container->getDefinition(PanelView::class)
            ->setArgument('$twig', new Reference('twig'))
            ->setArgument('$pathPrefix', $pathPrefix)
            ->setArgument('$layoutTemplate', $config['web_ui']['layout_template'])
            ->setArgument('$templates', $pageTemplates);

        $container->getDefinition(MaintenancePanelController::class)
            ->setArgument('$manager', new Reference(MaintenanceManager::class))
            ->setArgument('$accessGate', new Reference(MaintenanceAccessGateInterface::class))
            ->setArgument('$formFactory', new Reference('form.factory'))
            ->setArgument('$view', new Reference(PanelView::class))
            ->setArgument('$csrfTokenManager', new Reference(CsrfTokenManagerInterface::class, ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
            ->setArgument('$accessChecker', new Reference(MaintenanceModeAccessCheckerInterface::class))
            ->setArgument('$allowUnauthenticated', (bool) $config['security']['allow_unauthenticated'])
            ->setArgument('$historyLimit', (int) $config['web_ui']['history_limit'])
            ->setArgument('$historyPerPage', (int) $config['web_ui']['history_per_page'])
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
            ->setArgument('$clock', new Reference(ClockInterface::class, ContainerBuilder::IGNORE_ON_INVALID_REFERENCE))
            ->setPublic(true);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function configureTwigExtension(ContainerBuilder $container, array $config): void
    {
        if (!$container->hasDefinition(TwigMaintenanceExtension::class)) {
            return;
        }

        // Deprecated 1.x globals (nowo_maintenance_mode_*), kept for host templates written for 1.x.
        $webUi = $config['web_ui'];
        $container->getDefinition(TwigMaintenanceExtension::class)
            ->setArgument('$layoutTemplate', $webUi['layout_template'])
            ->setArgument('$cssFramework', $webUi['css_framework'] ?? 'custom')
            ->setArgument('$iconSet', $webUi['icon_set'] ?? 'none')
            ->setArgument('$chrome', [
                'show_page_header' => (bool) ($webUi['show_page_header'] ?? true),
                'document_title'   => (bool) ($webUi['document_title'] ?? true),
                'test_ids'         => (bool) ($webUi['test_ids'] ?? false),
                'classes'          => $webUi['classes'],
                'partials'         => $webUi['partials'],
            ])
            ->setArgument('$page', $config['page'] + [
                'show_preview_link' => (bool) ($webUi['show_preview_link'] ?? false),
                'form_theme'        => $webUi['form_theme'] ?? null,
            ]);
    }
}
