<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Panel;

use InvalidArgumentException;
use Nowo\MaintenanceModeBundle\Panel\PanelView;
use Nowo\UiKitBundle\View\AreaNav;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class PanelViewTest extends TestCase
{
    public function testTemplatesDefaultAndOverride(): void
    {
        $view = new PanelView($this->twig(), templates: ['history' => 'admin/history.html.twig']);

        self::assertSame(PanelView::TEMPLATES['index'], $view->template('index'));
        self::assertSame('admin/history.html.twig', $view->template('history'));

        $this->expectException(InvalidArgumentException::class);
        $view->template('settings');
    }

    public function testLayoutIsNullCanonicalOrTheLegacyBridge(): void
    {
        self::assertNull((new PanelView($this->twig()))->layout());
        self::assertSame('canonical.html.twig', (new PanelView($this->twig(), layoutTemplate: 'canonical.html.twig'))->layout());
        self::assertSame(PanelView::LEGACY_LAYOUT_BRIDGE, (new PanelView($this->twig(), layoutTemplate: 'legacy.html.twig'))->layout());
    }

    public function testHrefsFollowThePathPrefix(): void
    {
        $view = new PanelView($this->twig(), '/ops/maintenance/');
        self::assertSame('/ops/maintenance/', $view->indexHref());
        self::assertSame('/ops/maintenance/history', $view->href('/history'));
        self::assertSame('/', (new PanelView($this->twig(), '/'))->indexHref());
    }

    public function testNavMarksTheCurrentSection(): void
    {
        $view = new PanelView($this->twig());

        $panel = $view->nav('panel');
        self::assertCount(2, $panel->items);
        self::assertTrue($panel->items[0]->active);
        self::assertFalse($panel->items[1]->active);
        self::assertSame('/_maintenance/history', $panel->items[1]->href);
        self::assertSame(['data-testid' => 'maintenance-panel-tabs'], $panel->attributes);
        self::assertSame(['tabler:tool', 'tabler:history'], [$panel->items[0]->icon, $panel->items[1]->icon]);
        self::assertTrue($view->nav('history')->items[1]->active);
    }

    public function testAreaNavProvider(): void
    {
        $view = new PanelView($this->twig());

        self::assertNull($view->areaNav('routing_kit'));
        $nav = $view->areaNav(PanelView::AREA);
        self::assertInstanceOf(AreaNav::class, $nav);
        self::assertNull($nav->active());
        self::assertSame('/_maintenance/history', $view->areaNav(PanelView::AREA, ['current' => '/_maintenance/history'])?->active()?->href);
    }

    public function testRenderAddsTheSharedContext(): void
    {
        $response = (new PanelView($this->twig(), templates: ['index' => 'page.html.twig']))->render('index', ['page' => 'p'], 201);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame('p|/_maintenance|', $response->getContent());
    }

    public function testRenderWrapsALegacyLayoutWithADeprecation(): void
    {
        $view = new PanelView($this->twig(), layoutTemplate: 'legacy.html.twig', templates: ['index' => 'page.html.twig', 'login' => 'page.html.twig']);

        self::assertSame('p|/_maintenance|', $view->render('login', ['page' => 'p'])->getContent(), 'The login page uses the guest layout, never the legacy bridge.');

        $this->expectUserDeprecationMessageMatches('/"legacy\.html\.twig" \(nowo_maintenance_mode\.web_ui\.layout_template\) does not render the UiKit canonical blocks/');
        self::assertSame('p|/_maintenance|legacy.html.twig', $view->render('index', ['page' => 'p'])->getContent());
    }

    private function twig(): Environment
    {
        return new Environment(new ArrayLoader([
            'canonical.html.twig' => '{% block nowo_ui_content %}{% endblock %}',
            'legacy.html.twig'    => '{% block body %}{% endblock %}',
            'page.html.twig'      => '{{ page }}|{{ path_prefix }}|{{ nowo_maintenance_mode_legacy_layout }}',
        ]));
    }
}
