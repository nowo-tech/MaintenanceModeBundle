<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Panel;

use InvalidArgumentException;
use Nowo\MaintenanceModeBundle\NowoMaintenanceModeBundle;
use Nowo\UiKitBundle\Navigation\AreaNavProviderInterface;
use Nowo\UiKitBundle\View\AreaNav;
use Nowo\UiKitBundle\View\AreaNavItem;
use Nowo\UiKitBundle\View\Label;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

use function array_key_exists;
use function array_keys;
use function implode;
use function is_string;
use function rtrim;
use function sprintf;
use function trigger_deprecation;

/**
 * Renders the panel pages on the UiKit page contract (REQ-UI-003): resolves the per-page template
 * ({@code web_ui.templates.<page>}), the layout override of the view models ({@code web_ui.layout_template}; null =
 * {@code nowo_ui_layout.admin}), the area navigation of the panel (Panel | History, {@code PageHeader::$tabs}, also
 * published to the UiKit {@code AreaNavRegistry} as area {@see self::AREA}) and the shared page context.
 *
 * Immutable and request-agnostic (worker safe).
 */
final readonly class PanelView implements AreaNavProviderInterface
{
    public const BUNDLE = NowoMaintenanceModeBundle::TRANSLATION_DOMAIN;

    /** Area code of the panel navigation in the UiKit area-nav registry ({@code nowo_ui_area_nav('maintenance_mode')}). */
    public const AREA = 'maintenance_mode';

    /**
     * Bridge used when {@code web_ui.layout_template} is a 1.x layout that renders {@code body} instead of the UiKit
     * canonical blocks (deprecated since 1.10).
     */
    public const LEGACY_LAYOUT_BRIDGE = '@NowoMaintenanceModeBundle/panel/_legacy_layout.html.twig';

    /**
     * Page id => default template (override with {@code web_ui.templates.<page>}).
     *
     * @var array<string, string>
     */
    public const TEMPLATES = [
        'index'   => '@NowoMaintenanceModeBundle/panel/index.html.twig',
        'history' => '@NowoMaintenanceModeBundle/panel/history.html.twig',
        'login'   => '@NowoMaintenanceModeBundle/panel/login.html.twig',
    ];

    /**
     * @param array<string, string> $templates web_ui.templates (page id => template)
     */
    public function __construct(
        private Environment $twig,
        public string $pathPrefix = '/_maintenance',
        private ?string $layoutTemplate = null,
        private array $templates = [],
    ) {
    }

    /**
     * Translation key of the bundle domain, translated by the UiKit scaffolds.
     *
     * @param array<string, mixed> $params
     */
    public static function label(string $key, array $params = []): Label
    {
        return new Label($key, NowoMaintenanceModeBundle::TRANSLATION_DOMAIN, $params);
    }

    public function template(string $page): string
    {
        if (!array_key_exists($page, self::TEMPLATES)) {
            throw new InvalidArgumentException(sprintf('Unknown maintenance panel page "%s" (%s).', $page, implode(', ', array_keys(self::TEMPLATES))));
        }

        return $this->templates[$page] ?? self::TEMPLATES[$page];
    }

    /**
     * The view-model layout of the admin pages: null (UiKit admin layout), the configured layout when it renders the
     * canonical blocks, or the legacy bridge around a 1.x {@code body} layout.
     */
    public function layout(): ?string
    {
        if ($this->layoutTemplate === null) {
            return null;
        }

        return $this->isLegacyLayout() ? self::LEGACY_LAYOUT_BRIDGE : $this->layoutTemplate;
    }

    /**
     * URL of the panel index route: the prefix with a trailing slash (Symfony imports the empty route path as
     * "{prefix}/"; the slash-less URL answers with a 301).
     */
    public function indexHref(): string
    {
        return rtrim($this->pathPrefix, '/') . '/';
    }

    public function href(string $path): string
    {
        return rtrim($this->pathPrefix, '/') . $path;
    }

    /**
     * Area navigation of the panel sections (Panel | History) for {@code PageHeader::$tabs}, with the current section
     * active ({@code panel} or {@code history}; any other value: none).
     */
    public function nav(string $current): AreaNav
    {
        return new AreaNav([
            new AreaNavItem(self::label('panel.nav.panel'), $this->indexHref(), $current === 'panel', ['data-testid' => 'maintenance-nav-panel']),
            new AreaNavItem(self::label('panel.nav.history'), $this->href('/history'), $current === 'history', ['data-testid' => 'maintenance-nav-history']),
        ], self::label('panel.nav.aria_label'), ['data-testid' => 'maintenance-panel-tabs']);
    }

    /**
     * The panel navigation for other bundles (DashboardMenu, host menus): area {@see self::AREA}; {@code context.current}
     * (an href) marks that item active.
     *
     * @param array<string, mixed> $context
     */
    public function areaNav(string $area, array $context = []): ?AreaNav
    {
        if ($area !== self::AREA) {
            return null;
        }

        $nav     = $this->nav('');
        $current = $context['current'] ?? null;

        return is_string($current) ? $nav->withActive($current) : $nav;
    }

    /**
     * @param array<string, mixed> $context page context (at least `page`, the view model)
     */
    public function render(string $page, array $context, int $status = Response::HTTP_OK): Response
    {
        $legacy = $page !== 'login' && $this->layoutTemplate !== null && $this->isLegacyLayout();
        if ($legacy) {
            trigger_deprecation('nowo-tech/maintenance-mode-bundle', '1.10', 'The layout "%s" (nowo_maintenance_mode.web_ui.layout_template) does not render the UiKit canonical blocks (nowo_ui_content…); it is wrapped in "%s". Render the canonical blocks in your layout (see docs/UPGRADING.md) or set web_ui.layout_template to null.', (string) $this->layoutTemplate, self::LEGACY_LAYOUT_BRIDGE);
        }

        return new Response($this->twig->render($this->template($page), $context + [
            'path_prefix'                         => $this->pathPrefix,
            'nowo_maintenance_mode_legacy_layout' => $legacy ? $this->layoutTemplate : null,
        ]), $status);
    }

    private function isLegacyLayout(): bool
    {
        return !$this->twig->load((string) $this->layoutTemplate)->hasBlock('nowo_ui_content');
    }
}
