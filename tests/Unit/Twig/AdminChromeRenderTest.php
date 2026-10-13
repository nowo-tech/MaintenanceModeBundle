<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function file_get_contents;

/**
 * Admin chrome hooks (spec 002): default output is byte-identical to the pre-1.7.0 templates,
 * and blocks / class map / partial paths / flags apply when set.
 */
final class AdminChromeRenderTest extends TestCase
{
    private const INDEX   = '@NowoMaintenanceModeBundle/panel/index.html.twig';
    private const HISTORY = '@NowoMaintenanceModeBundle/panel/history.html.twig';
    private const LOGIN   = '@NowoMaintenanceModeBundle/panel/login.html.twig';
    private const PAGE    = '@NowoMaintenanceModeBundle/maintenance/page.html.twig';

    /**
     * @return iterable<string, array{string, string, array<string, mixed>}>
     */
    public static function goldenProvider(): iterable
    {
        yield 'index' => ['panel_index.html', self::INDEX, TemplateRenderHarness::indexContext()];
        yield 'history' => ['panel_history.html', self::HISTORY, TemplateRenderHarness::historyContext()];
        yield 'history empty' => ['panel_history_empty.html', self::HISTORY, TemplateRenderHarness::historyContext(false)];
        yield 'login' => ['panel_login.html', self::LOGIN, TemplateRenderHarness::loginContext()];
        yield 'public page' => ['maintenance_page.html', self::PAGE, TemplateRenderHarness::pageContext()];
    }

    /**
     * Golden files were rendered from the 1.6.1 templates (before any hook existed).
     *
     * @param array<string, mixed> $context
     */
    #[DataProvider('goldenProvider')]
    public function testDefaultOutputIsUnchanged(string $golden, string $template, array $context): void
    {
        $expected = (string) file_get_contents(TemplateRenderHarness::FIXTURE_DIR . '/' . $golden);

        self::assertSame($expected, TemplateRenderHarness::environment()->render($template, $context));
    }

    /**
     * @param array<string, mixed> $context
     */
    #[DataProvider('goldenProvider')]
    public function testExplicitDefaultChromeMatchesGolden(string $golden, string $template, array $context): void
    {
        $expected = (string) file_get_contents(TemplateRenderHarness::FIXTURE_DIR . '/' . $golden);

        self::assertSame($expected, TemplateRenderHarness::environment([])->render($template, $context));
    }

    public function testHistoryClassMapTestIdsAndHiddenHeader(): void
    {
        $html = TemplateRenderHarness::environment([
            'show_page_header' => false,
            'test_ids'         => true,
            'classes'          => [
                'table_wrap'       => 'panel overflow-x-auto',
                'table'            => 'data-table w-full nowo-ui-table',
                'table_head_row'   => 'border-b',
                'table_head_cell'  => 'px-2 py-2 font-medium',
                'table_row'        => 'row-hover',
                'table_cell'       => 'px-2 py-3',
                'table_cell_muted' => 'text-muted',
                'nav'              => 'tabs',
                'nav_link'         => 'tab',
            ],
        ])->render(self::HISTORY, TemplateRenderHarness::historyContext());

        self::assertStringNotContainsString('<header', $html);
        self::assertStringContainsString('<div class="panel overflow-x-auto" data-testid="maintenance-history">', $html);
        self::assertStringContainsString('<table class="data-table w-full nowo-ui-table">', $html);
        self::assertStringContainsString('<tr class="border-b">', $html);
        self::assertStringContainsString('<th class="px-2 py-2 font-medium">When</th>', $html);
        self::assertStringContainsString('<tr class="row-hover">', $html);
        self::assertStringContainsString('<td class="px-2 py-3">enable</td>', $html);
        self::assertStringContainsString('<td class="px-2 py-3 text-muted">Deploy</td>', $html);
        self::assertStringContainsString('<nav class="tabs" aria-label="Panel" data-testid="maintenance-panel-tabs">', $html);
        self::assertStringContainsString('<a class="tab" href="/_maintenance/history">History</a>', $html);
    }

    public function testHistoryEmptyStateClassAndPaginationPartial(): void
    {
        $html = TemplateRenderHarness::environment(
            ['classes' => ['empty' => 'empty px-2'], 'partials' => ['pagination' => 'host/pagination.html.twig']],
            ['host/pagination.html.twig' => '<nav class="pager">{{ entries|length }} @ {{ path_prefix }}</nav>'],
        )->render(self::HISTORY, TemplateRenderHarness::historyContext(false));

        self::assertStringContainsString('<td class="empty px-2" colspan="4">No history yet.</td>', $html);
        self::assertStringContainsString('<nav class="pager">0 @ /_maintenance</nav>', $html);
    }

    public function testEmptyClassValuesDropTheAttribute(): void
    {
        $html = TemplateRenderHarness::environment([
            'classes' => ['table_wrap' => '', 'table' => '', 'empty' => '', 'nav' => '', 'page_header' => ''],
        ])->render(self::HISTORY, TemplateRenderHarness::historyContext(false));

        self::assertStringContainsString("<div>\n        <table>", $html);
        self::assertStringContainsString('<tr><td colspan="4">', $html);
        self::assertStringContainsString('<nav aria-label="Panel">', $html);
        self::assertStringContainsString("<header>\n", $html);
        self::assertStringNotContainsString('class=""', $html);
    }

    public function testIndexClassMapFormActionsAndTestIds(): void
    {
        $html = TemplateRenderHarness::environment([
            'test_ids' => true,
            'classes'  => [
                'card'          => 'panel mb-6',
                'flash'         => 'mb-4',
                'status_line'   => 'status-line',
                'status_on'     => 'is-on',
                'section_title' => 'nowo-ui-section-title',
                'form'          => 'nowo-ui-form--stack',
                'form_actions'  => 'nowo-ui-form-actions',
                'page_header'   => 'hdr',
                'page_title'    => 'ttl',
            ],
        ])->render(self::INDEX, TemplateRenderHarness::indexContext());

        self::assertStringContainsString('<header class="hdr">', $html);
        self::assertStringContainsString('<h1 class="ttl">Maintenance panel</h1>', $html);
        self::assertStringContainsString('class="nowo-ui-flash nowo-ui-flash--success mb-4"', $html);
        self::assertStringContainsString('<p class="status-line" data-testid="maintenance-status">', $html);
        self::assertStringContainsString('<span class="is-on">ENABLED</span>', $html);
        self::assertStringContainsString('<div class="panel mb-6" data-testid="maintenance-manual">', $html);
        self::assertStringContainsString('<div class="panel mb-6" data-testid="maintenance-schedule">', $html);
        self::assertStringContainsString('<h2 class="nowo-ui-section-title">Schedule</h2>', $html);
        self::assertStringContainsString('action="/_maintenance/enable" class="nowo-ui-form--stack"', $html);
        self::assertStringContainsString('style="margin-top:1rem" class="nowo-ui-form--stack"', $html);
        self::assertMatchesRegularExpression('#<div class="nowo-ui-form-actions">\s*<button type="submit" class="nowo-ui-btn nowo-ui-btn--primary">#', $html);
    }

    /**
     * 1.7.0 fix: `password_required ?? true` (was `|default(true)`, which treated false as empty and
     * showed the logout button without a password gate). Missing variable still defaults to shown.
     */
    public function testIndexLogoutFollowsPasswordRequired(): void
    {
        $twig = TemplateRenderHarness::environment();

        $noGate = $twig->render(self::INDEX, TemplateRenderHarness::indexContext(false));
        self::assertStringNotContainsString('/_maintenance/logout', $noGate);
        self::assertStringContainsString('nowo-ui-status--on', $noGate);

        self::assertStringContainsString('/_maintenance/logout', $twig->render(self::INDEX, TemplateRenderHarness::indexContext()));

        $context = TemplateRenderHarness::indexContext();
        unset($context['password_required']);
        self::assertStringContainsString('/_maintenance/logout', $twig->render(self::INDEX, $context));

        $host  = "{% extends '@NowoMaintenanceModeBundle/panel/index.html.twig' %}{% block maintenance_logout_form %}{% endblock %}";
        $empty = TemplateRenderHarness::environment(null, ['host/index.html.twig' => $host])
            ->render('host/index.html.twig', TemplateRenderHarness::indexContext());
        self::assertStringNotContainsString('/_maintenance/logout', $empty);
    }

    public function testIndexDisabledStateUsesStatusOffClass(): void
    {
        $context          = TemplateRenderHarness::indexContext();
        $context['state'] = TemplateRenderHarness::state()->withEnabled(false);

        $html = TemplateRenderHarness::environment(['classes' => ['status_off' => 'is-off']])->render(self::INDEX, $context);

        self::assertStringContainsString('<span class="is-off">disabled</span>', $html);
        self::assertStringContainsString('<span class="is-off">off</span>', $html);
    }

    public function testNavAndFormFieldsPartialPathsAreConfigurable(): void
    {
        $html = TemplateRenderHarness::environment(
            ['partials' => ['nav' => 'host/nav.html.twig', 'form_fields' => 'host/fields.html.twig']],
            [
                'host/nav.html.twig'    => '<nav data-host="1">{{ active }}|{{ path_prefix }}</nav>',
                'host/fields.html.twig' => '<i data-fields="{{ form.vars.action }}"></i>',
            ],
        )->render(self::INDEX, TemplateRenderHarness::indexContext());

        self::assertStringContainsString('<nav data-host="1">panel|/_maintenance</nav>', $html);
        self::assertStringContainsString('<i data-fields="/_maintenance/enable"></i>', $html);
        self::assertStringNotContainsString('class="nowo-ui-nav"', $html);
    }

    public function testShippedUiKitTabsNavMarksCurrentTab(): void
    {
        $twig = TemplateRenderHarness::environment(['partials' => ['nav' => '@NowoMaintenanceModeBundle/panel/_nav_tabs.html.twig']]);

        $history = $twig->render(self::HISTORY, TemplateRenderHarness::historyContext());
        self::assertMatchesRegularExpression('#href="/_maintenance/history"\s+aria-current="page"#', $history);
        self::assertStringContainsString('aria-label="Panel"', $history);

        $index = $twig->render(self::INDEX, TemplateRenderHarness::indexContext());
        self::assertMatchesRegularExpression('#href="/_maintenance"\s+aria-current="page"#', $index);
    }

    public function testHostTemplateCanOverrideIndexBlocks(): void
    {
        $host = <<<'TWIG'
            {% extends '@NowoMaintenanceModeBundle/panel/index.html.twig' %}
            {% block maintenance_header_actions %}<a class="act" href="/preview">Preview</a>{% endblock %}
            {% block maintenance_page_intro %}<p class="intro">Intro</p>{% endblock %}
            {% block maintenance_status %}<dl class="maintenance-status"><dt>Manual</dt><dd>{{ state.enabled ? 'on' : 'off' }}</dd></dl>{% endblock %}
            {% block maintenance_state_details %}{% endblock %}
            {% block maintenance_logout_form %}{% endblock %}
            TWIG;

        $html = TemplateRenderHarness::environment(null, ['host/index.html.twig' => $host])
            ->render('host/index.html.twig', TemplateRenderHarness::indexContext());

        self::assertStringContainsString('<a class="act" href="/preview">Preview</a>', $html);
        self::assertStringContainsString('<p class="intro">Intro</p>', $html);
        self::assertStringContainsString('<dl class="maintenance-status"><dt>Manual</dt><dd>on</dd></dl>', $html);
        self::assertStringNotContainsString('Updated by', $html);
        self::assertStringNotContainsString('/_maintenance/logout', $html);
        self::assertStringContainsString('/_maintenance/enable', $html);
    }

    public function testHostTemplateCanOverrideHistoryRowBlocks(): void
    {
        $host = <<<'TWIG'
            {% extends '@NowoMaintenanceModeBundle/panel/history.html.twig' %}
            {% block maintenance_toolbar %}<form class="toolbar"></form>{% endblock %}
            {% block maintenance_table_head_actions %}<th>Actions</th>{% endblock %}
            {% block maintenance_table_row_actions %}<td><a href="#{{ entry.action }}">View</a></td>{% endblock %}
            {% block maintenance_empty_state %}<tr><td colspan="5">Nothing</td></tr>{% endblock %}
            TWIG;

        $twig = TemplateRenderHarness::environment(null, ['host/history.html.twig' => $host]);
        $html = $twig->render('host/history.html.twig', TemplateRenderHarness::historyContext());

        self::assertStringContainsString('<form class="toolbar"></form>', $html);
        self::assertStringContainsString('<th>Actions</th>', $html);
        self::assertStringContainsString('<td><a href="#enable">View</a></td>', $html);
        self::assertStringContainsString('<td><a href="#disable">View</a></td>', $html);

        $empty = $twig->render('host/history.html.twig', TemplateRenderHarness::historyContext(false));
        self::assertStringContainsString('<tr><td colspan="5">Nothing</td></tr>', $empty);
    }

    public function testDocumentTitleFalseKeepsLayoutTitle(): void
    {
        $twig = TemplateRenderHarness::environment(['document_title' => false]);

        self::assertStringContainsString('<title>Maintenance panel</title>', $twig->render(self::HISTORY, TemplateRenderHarness::historyContext()));
        self::assertStringContainsString('<title>Maintenance panel</title>', $twig->render(self::INDEX, TemplateRenderHarness::indexContext()));
        self::assertStringContainsString('<title>Maintenance panel</title>', $twig->render(self::LOGIN, TemplateRenderHarness::loginContext()));
        self::assertStringContainsString('<title>History</title>', TemplateRenderHarness::environment()->render(self::HISTORY, TemplateRenderHarness::historyContext()));
    }

    public function testLoginHooks(): void
    {
        $html = TemplateRenderHarness::environment([
            'show_page_header' => false,
            'classes'          => ['page_intro' => 'lead', 'login_error' => 'alert', 'form_actions' => 'actions'],
        ])->render(self::LOGIN, TemplateRenderHarness::loginContext());

        self::assertStringNotContainsString('<header', $html);
        self::assertStringContainsString('<p class="lead">Enter the maintenance password', $html);
        self::assertStringContainsString('<p class="alert">Invalid password.</p>', $html);
        self::assertMatchesRegularExpression('#<div class="actions">\s*<button#', $html);

        $noError = TemplateRenderHarness::environment()->render(self::LOGIN, TemplateRenderHarness::loginContext(null));
        self::assertStringNotContainsString('Invalid password.', $noError);
    }

    public function testPublicPageBlocksCanBeOverridden(): void
    {
        $host = <<<'TWIG'
            {% extends '@NowoMaintenanceModeBundle/maintenance/page.html.twig' %}
            {% block maintenance_html_attributes %} data-theme="dark"{% endblock %}
            {% block title %}Brand — {{ parent() }}{% endblock %}
            {% block maintenance_fonts %}{% endblock %}
            {% block maintenance_head_extra %}<link rel="icon" href="/favicon.ico">{% endblock %}
            {% block maintenance_body_attributes %} class="brand"{% endblock %}
            {% block maintenance_brand %}<img src="/logo.svg" alt="Brand">{% endblock %}
            {% block maintenance_footer %}<p class="eta">Soon</p>{% endblock %}
            {% block maintenance_scripts %}<script>/* countdown */</script>{% endblock %}
            TWIG;

        $html = TemplateRenderHarness::environment(null, ['host/page.html.twig' => $host])
            ->render('host/page.html.twig', TemplateRenderHarness::pageContext());

        self::assertStringContainsString('<html lang="en" data-theme="dark">', $html);
        self::assertStringContainsString('<title>Brand — A short pause</title>', $html);
        self::assertStringNotContainsString('fonts.googleapis.com', $html);
        self::assertStringContainsString('<link rel="icon" href="/favicon.ico">', $html);
        self::assertStringContainsString('<body class="brand">', $html);
        self::assertStringContainsString('<img src="/logo.svg" alt="Brand">', $html);
        self::assertStringNotContainsString('class="badge"', $html);
        self::assertStringContainsString('<p class="message">Back soon</p>', $html);
        self::assertStringContainsString('<p class="eta">Soon</p>', $html);
        self::assertStringContainsString('<script>/* countdown */</script>', $html);
    }
}
