<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\DependencyInjection;

use InvalidArgumentException;
use Nowo\MaintenanceModeBundle\Panel\PanelView;
use Nowo\MaintenanceModeBundle\Twig\PanelChrome;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function implode;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;

/**
 * Bundle configuration tree under alias `nowo_maintenance_mode`.
 */
final class Configuration implements ConfigurationInterface
{
    public const ALIAS = 'nowo_maintenance_mode';

    /** Default panel URL prefix (web_ui.path_prefix). */
    public const DEFAULT_PATH_PREFIX = '/_maintenance';

    /** Default public maintenance page (templates.page). */
    public const DEFAULT_PAGE_TEMPLATE = '@NowoMaintenanceModeBundle/maintenance/page.html.twig';

    /** 1.x default of web_ui.layout_template / templates.panel_layout (removed template); mapped to null. */
    public const LEGACY_LAYOUT_TEMPLATE = '@NowoMaintenanceModeBundle/panel/layout.html.twig';

    /**
     * Deprecated templates.panel_* key => web_ui.templates page.
     *
     * @var array<string, string>
     */
    public const LEGACY_TEMPLATE_KEYS = [
        'panel_index'   => 'index',
        'panel_login'   => 'login',
        'panel_history' => 'history',
    ];

    private const DEPRECATION_PACKAGE = 'nowo-tech/maintenance-mode-bundle';

    private const DEPRECATION_VERSION = '1.10';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder(self::ALIAS);
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->info('Nowo Maintenance Mode Bundle configuration.')
            ->beforeNormalization()
                ->ifArray()
                ->then(static fn (array $v): array => self::mapDeprecatedAliases($v))
            ->end()
            ->children()
                ->booleanNode('enabled')
                    ->info('Master switch: when false the subscriber never returns 503.')
                    ->defaultTrue()
                ->end()
                ->scalarNode('default_message')
                    ->info('Default message shown on the public maintenance page. null = rely on Twig translation (maintenance.page.message).')
                    ->defaultValue("We're making a few gentle improvements. Everything you care about is safe.")
                ->end()
                ->integerNode('status_code')
                    ->info('HTTP status code for the maintenance response.')
                    ->defaultValue(503)
                    ->min(400)
                    ->max(599)
                ->end()
                ->integerNode('retry_after')
                    ->info('Retry-After header value in seconds.')
                    ->defaultValue(3600)
                    ->min(0)
                ->end()
                ->integerNode('subscriber_priority')
                    ->info('kernel.request listener priority (default 31: after router so route/controller exclusions work).')
                    ->defaultValue(31)
                ->end()
                ->arrayNode('preview')
                    ->info('Dev preview of the configured public maintenance page (like /_error/503).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->variableNode('enabled')
                            ->info('null = enable when kernel.debug is true. Set false to disable even in debug.')
                            ->defaultNull()
                            ->validate()
                                ->ifTrue(static fn (mixed $v): bool => $v !== null && !is_bool($v))
                                ->thenInvalid('preview.enabled must be null or a boolean.')
                            ->end()
                        ->end()
                        ->scalarNode('path')
                            ->info('URL path for the preview (auto-excluded from 503).')
                            ->defaultValue('/_maintenance_preview')
                            ->cannotBeEmpty()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('panel')
                    ->info('Deprecated since 1.10: use web_ui.enabled and web_ui.path_prefix.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Deprecated alias of web_ui.enabled.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_maintenance_mode.web_ui.enabled" instead.')
                        ->end()
                        ->scalarNode('path_prefix')
                            ->info('Deprecated alias of web_ui.path_prefix.')
                            ->cannotBeEmpty()
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_maintenance_mode.web_ui.path_prefix" instead.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('web_ui')
                    ->info('REQ-UI-003 admin panel UI: pages extend the UiKit scaffolds; theme them once in nowo_ui_kit.theme.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Expose the admin panel routes (panel, history, password login).')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('path_prefix')
                            ->info('URL prefix of the admin panel (always excluded from the 503).')
                            ->defaultValue(self::DEFAULT_PATH_PREFIX)
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('layout_template')
                            ->info('Layout the panel scaffolds extend instead of nowo_ui_kit.layouts.admin (null = UiKit layout). It must render the UiKit canonical blocks (nowo_ui_content…); a 1.x layout that renders "body" is bridged (deprecated). The password login uses the guest layout.')
                            ->defaultNull()
                        ->end()
                        ->arrayNode('templates')
                            ->info('Per-page template override (page => template): ' . implode(', ', array_keys(PanelView::TEMPLATES)) . '. Prefer a thin {% extends \'@NowoMaintenanceModeBundle/panel/…\' %} that overrides a data block.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('page')
                            ->scalarPrototype()->cannotBeEmpty()->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => array_diff(array_keys($v), array_keys(PanelView::TEMPLATES)) !== [])
                                ->then(static function (array $v): array {
                                    throw new InvalidArgumentException(sprintf('Unknown nowo_maintenance_mode.web_ui.templates page(s): %s. Allowed: %s.', implode(', ', array_diff(array_keys($v), array_keys(PanelView::TEMPLATES))), implode(', ', array_keys(PanelView::TEMPLATES))));
                                })
                            ->end()
                        ->end()
                        ->integerNode('history_limit')
                            ->info('Most recent history entries listed on the history page.')
                            ->defaultValue(100)
                            ->min(1)
                        ->end()
                        ->integerNode('history_per_page')
                            ->info('History entries per page (UiKit pagination).')
                            ->defaultValue(25)
                            ->min(1)
                        ->end()
                        ->enumNode('css_framework')
                            ->info('Deprecated: set nowo_ui_kit.css_framework (seeded from this key when the host does not set it).')
                            ->values(['bootstrap', 'bootstrap4', 'bootstrap5', 'tailwind', 'foundation', 'custom', 'tabler', 'none'])
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, set "nowo_ui_kit.css_framework" instead.')
                        ->end()
                        ->enumNode('icon_set')
                            ->info('Deprecated: set nowo_ui_kit.icon_set (seeded from this key when the host does not set it).')
                            ->values(['bootstrap-icons', 'tabler-icons', 'ux_icon', 'svg_inline', 'none'])
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, set "nowo_ui_kit.icon_set" instead.')
                        ->end()
                        ->booleanNode('show_page_header')
                            ->info('Deprecated, no effect: the UiKit page_header partial renders the header (hide or restyle it in nowo_ui_kit.theme).')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated and has no effect: the UiKit page_header partial renders the page header.')
                        ->end()
                        ->booleanNode('document_title')
                            ->info('Deprecated, no effect: the scaffolds fill nowo_ui_document_title.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated and has no effect: the scaffolds fill nowo_ui_document_title.')
                        ->end()
                        ->booleanNode('test_ids')
                            ->info('Deprecated, no effect: the data-testid hooks are always rendered.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated and has no effect: the data-testid hooks are always rendered.')
                        ->end()
                        ->arrayNode('classes')
                            ->info('Deprecated 1.x class map, bridged into nowo_ui_kit.theme.bundles.NowoMaintenanceModeBundle.classes (see docs/UPGRADING.md). Keys: ' . implode(', ', PanelChrome::CLASS_KEYS) . '.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_ui_kit.theme.bundles.NowoMaintenanceModeBundle.classes" (UiKit theme slots) instead.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->scalarPrototype()->end()
                            ->validate()
                                ->ifTrue(static fn (array $v): bool => array_diff(array_keys($v), PanelChrome::CLASS_KEYS) !== [])
                                ->then(static function (array $v): array {
                                    throw new InvalidArgumentException(sprintf('Unknown nowo_maintenance_mode.web_ui.classes key(s): %s. Allowed: %s.', implode(', ', array_diff(array_keys($v), PanelChrome::CLASS_KEYS)), implode(', ', PanelChrome::CLASS_KEYS)));
                                })
                            ->end()
                        ->end()
                        ->arrayNode('partials')
                            ->info('Deprecated 1.x partial map (nav, form_fields, pagination), ignored: the scaffolds render the area nav, the form rows and the UiKit pagination (replace UiKit partials in nowo_ui_kit.theme.partials).')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated and has no effect: replace UiKit partials in "nowo_ui_kit.theme.partials" (or "theme.bundles.NowoMaintenanceModeBundle.partials") instead.')
                            ->normalizeKeys(false)
                            ->useAttributeAsKey('name')
                            ->variablePrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('exclusions')
                    ->info('Requests matching any rule bypass the maintenance page.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('paths')
                            ->info('Exact path matches (e.g. /health).')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('path_prefixes')
                            ->info('Segment-aware path prefixes: /api matches /api and /api/… but not /apix. Append * (e.g. /api*) for a raw string prefix. Panel prefix is always added.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('routes')
                            ->info('Route names that bypass maintenance.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('patterns')
                            ->info('Glob (fnmatch) or regex (#…# or ~…~) patterns against the request path.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                        ->arrayNode('ips')
                            ->info('Client IPs or CIDR ranges that bypass maintenance (configure framework.trusted_proxies when behind a proxy).')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('security')
                    ->info('REQ-UI-002 roles + optional ops password gate / soft bypass. Password gate is additional to access_roles.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('access_roles')
                            ->info('Symfony roles granted access to the panel (at least one). Empty list is fail-closed (deny via access checker). Use allow_unauthenticated for demos only.')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ROLE_ADMIN'])
                        ->end()
                        ->scalarNode('access_checker')
                            ->info('Optional service id implementing MaintenanceModeAccessCheckerInterface. null = role-based default (or AllowAll when allow_unauthenticated).')
                            ->defaultNull()
                        ->end()
                        ->booleanNode('allow_unauthenticated')
                            ->info('DEV/DEMO only: skip Symfony Security role check (password gate may still apply). Never true in production.')
                            ->defaultFalse()
                        ->end()
                        ->booleanNode('password_protection')
                            ->info('When false, the panel login section is disabled even if a hash is set (trusted networks only).')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('password_hash')
                            ->info('password_hash() output (bcrypt / argon2id / sodium). Prefer env: MAINTENANCE_PASSWORD_HASH.')
                            ->defaultNull()
                        ->end()
                        ->integerNode('login_max_attempts')
                            ->info('Failed panel password attempts allowed per session / client IP within login_lockout_seconds (0 = unlimited).')
                            ->min(0)
                            ->defaultValue(5)
                        ->end()
                        ->integerNode('login_lockout_seconds')
                            ->info('Window (seconds) for counting failed panel password attempts.')
                            ->min(1)
                            ->defaultValue(900)
                        ->end()
                        ->scalarNode('access_gate')
                            ->info('FQCN or service id implementing MaintenanceAccessGateInterface. null = default password gate.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('bypass_token')
                            ->info('Optional shared secret: ?maintenance_bypass=TOKEN (or cookie) skips the 503 for QA without opening the panel.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('bypass_query_parameter')
                            ->info('Query parameter name for the soft bypass token.')
                            ->defaultValue('maintenance_bypass')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('bypass_cookie_name')
                            ->info('Cookie name set after a successful query bypass.')
                            ->defaultValue('nowo_maintenance_bypass')
                            ->cannotBeEmpty()
                        ->end()
                        ->booleanNode('bypass_set_cookie')
                            ->info('When true, a successful query bypass also sets a cookie for subsequent requests.')
                            ->defaultTrue()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('storage')
                    ->info('Pluggable state / history backends (default: filesystem under var/).')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('state_file')
                            ->info('JSON or YAML file for MaintenanceState.')
                            ->defaultValue('%kernel.project_dir%/var/maintenance/state.json')
                        ->end()
                        ->scalarNode('history_file')
                            ->info('Append-only JSONL history file.')
                            ->defaultValue('%kernel.project_dir%/var/maintenance/history.jsonl')
                        ->end()
                        ->scalarNode('state_storage')
                            ->info('Override service id / FQCN for MaintenanceStateStorageInterface.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('history_storage')
                            ->info('Override service id / FQCN for MaintenanceHistoryStorageInterface.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('templates')
                    ->info('Public maintenance page template (REQ-TWIG); the panel_* keys are deprecated aliases of web_ui.layout_template / web_ui.templates.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('page')
                            ->info('Public 503 page. Default: UiKit guest layout + theme tokens, no CDN. Samples: @NowoMaintenanceModeBundle/maintenance/examples/*.html.twig (opt-in, they load CDNs).')
                            ->defaultValue(self::DEFAULT_PAGE_TEMPLATE)
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('panel_layout')
                            ->info('Deprecated alias of web_ui.layout_template.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_maintenance_mode.web_ui.layout_template" instead (null = UiKit layout).')
                        ->end()
                        ->scalarNode('panel_index')
                            ->info('Deprecated alias of web_ui.templates.index.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_maintenance_mode.web_ui.templates.index" instead.')
                        ->end()
                        ->scalarNode('panel_login')
                            ->info('Deprecated alias of web_ui.templates.login.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_maintenance_mode.web_ui.templates.login" instead.')
                        ->end()
                        ->scalarNode('panel_history')
                            ->info('Deprecated alias of web_ui.templates.history.')
                            ->setDeprecated(self::DEPRECATION_PACKAGE, self::DEPRECATION_VERSION, 'The "%path%.%node%" option is deprecated, use "nowo_maintenance_mode.web_ui.templates.history" instead.')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }

    /**
     * REQ-UI-003 canonical keys: copies the deprecated aliases (panel.enabled, panel.path_prefix,
     * templates.panel_layout, templates.panel_*) into web_ui.* when the canonical key is not set (the canonical key
     * wins). The deprecated keys stay in place so their deprecation is reported. The 1.x default layout (a standalone
     * HTML page, removed) and the 1.x default page templates map to "no override".
     *
     * @param array<array-key, mixed> $v
     *
     * @return array<array-key, mixed>
     */
    private static function mapDeprecatedAliases(array $v): array
    {
        $panel     = is_array($v['panel'] ?? null) ? $v['panel'] : [];
        $templates = is_array($v['templates'] ?? null) ? $v['templates'] : [];
        $webUi     = is_array($v['web_ui'] ?? null) ? $v['web_ui'] : [];

        foreach (['enabled', 'path_prefix'] as $key) {
            if (array_key_exists($key, $panel) && !array_key_exists($key, $webUi)) {
                $webUi[$key] = $panel[$key];
            }
        }

        if (array_key_exists('panel_layout', $templates) && !array_key_exists('layout_template', $webUi)) {
            $webUi['layout_template'] = $templates['panel_layout'];
        }
        if (($webUi['layout_template'] ?? null) === self::LEGACY_LAYOUT_TEMPLATE) {
            $webUi['layout_template'] = null;
        }

        $pages = is_array($webUi['templates'] ?? null) ? $webUi['templates'] : [];
        foreach (self::LEGACY_TEMPLATE_KEYS as $legacy => $page) {
            $template = $templates[$legacy] ?? null;
            if (is_string($template) && $template !== '' && $template !== PanelView::TEMPLATES[$page] && !array_key_exists($page, $pages)) {
                $pages[$page] = $template;
            }
        }
        if ($pages !== []) {
            $webUi['templates'] = $pages;
        }

        if ($webUi !== []) {
            $v['web_ui'] = $webUi;
        }

        return $v;
    }
}
