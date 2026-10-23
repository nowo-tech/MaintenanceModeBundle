<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Integration\Contract;

use Nowo\MaintenanceModeBundle\Panel\PanelView;
use Nowo\MaintenanceModeBundle\Tests\Support\PageFixtures;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;
use Nowo\UiKitBundle\View\ListPage;
use Nowo\UiKitBundle\View\SettingsPage;

use function dirname;
use function preg_match_all;
use function substr_count;

/**
 * REQ-UI-003: every bundle page extends a UiKit scaffold (or is a declared unique page on the UiKit layout), fills
 * the canonical blocks, ships no chrome of its own (no <html>, CDN, inline handlers, framework classes outside the
 * theme slots) and renders translated labels only.
 */
final class PageContractTest extends PageContractTestCase
{
    public static function pageFixtures(): iterable
    {
        yield 'panel (maintenance on, password gate)' => new PageFixture('@NowoMaintenanceModeBundle/panel/index.html.twig', PageFixtures::index(PageFixtures::enabledState()));
        yield 'panel (maintenance off, scheduled, no password gate)' => new PageFixture('@NowoMaintenanceModeBundle/panel/index.html.twig', PageFixtures::index(PageFixtures::disabledState(), false));
        yield 'history' => new PageFixture('@NowoMaintenanceModeBundle/panel/history.html.twig', PageFixtures::history(PageFixtures::entries()));
        yield 'history (page 2 of 3)' => new PageFixture('@NowoMaintenanceModeBundle/panel/history.html.twig', PageFixtures::history(PageFixtures::entries(7), 2, 3));
        yield 'history (empty)' => new PageFixture('@NowoMaintenanceModeBundle/panel/history.html.twig', PageFixtures::history([]));
        yield 'login' => new PageFixture('@NowoMaintenanceModeBundle/panel/login.html.twig', PageFixtures::login());
        yield 'login (wrong password)' => new PageFixture('@NowoMaintenanceModeBundle/panel/login.html.twig', PageFixtures::login('Invalid password.'));
        yield 'login (locked out)' => new PageFixture('@NowoMaintenanceModeBundle/panel/login.html.twig', PageFixtures::login('Too many failed attempts.', true));
        yield 'public maintenance page' => new PageFixture('@NowoMaintenanceModeBundle/maintenance/page.html.twig', PageFixtures::maintenance('Database upgrade in progress — back at 14:30.'));
        yield 'public maintenance page (default message)' => new PageFixture('@NowoMaintenanceModeBundle/maintenance/page.html.twig', PageFixtures::maintenance());
    }

    protected static function inlineTemplates(): array
    {
        return [
            // 1.x host layout (renders `body`): wrapped by the deprecated legacy bridge.
            'host/legacy.html.twig' => '<legacy-layout><title>{% block title %}{% endblock %}</title><head-css>{% block stylesheets %}<host-css/>{% endblock %}</head-css>{% block body %}{% endblock %}<js>{% block javascripts %}{% endblock %}</js></legacy-layout>',
            // The UiKit page header partial itself (the stub host's page_header override does not render header.tabs).
            'host/page_header.html.twig' => "{{ include('@NowoUiKitBundle/partials/_page_header.html.twig', {header: page.header, bundle: page.bundle}) }}",
            // Thin app override (web_ui.templates.history) of a data block.
            'host/history_override.html.twig' => "{% extends '@NowoMaintenanceModeBundle/panel/history.html.twig' %}{% block nowo_ui_list_after %}<p class=\"host-note\">Kept 90 days.</p>{% endblock %}",
            // 1.7 public page hooks (templates.page extending the bundle page).
            'host/maintenance.html.twig' => "{% extends '@NowoMaintenanceModeBundle/maintenance/page.html.twig' %}{% block maintenance_heading %}<h1>Acme is upgrading</h1>{% endblock %}{% block maintenance_styles %}<link rel=\"stylesheet\" href=\"/css/acme-maintenance.css\">{% endblock %}{% block maintenance_scripts %}<script src=\"/js/acme.js\" defer></script>{% endblock %}",
            // templates.page on the host's auth scaffold with the `page` view model.
            'host/maintenance_auth.html.twig' => "{% extends '@NowoUiKit/page/auth.html.twig' %}",
            // web_ui.form_theme (1.12): a host form theme for the panel section forms.
            'host/form_theme.html.twig' => "{% use 'form_div_layout.html.twig' %}{% block form_widget_simple %}<input data-host-form-theme type=\"{{ type|default('text') }}\" {{ block('widget_attributes') }}>{% endblock %}",
        ];
    }

    public function testPanelRendersStatusFormsAndSectionActions(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/panel/index.html.twig', PageFixtures::index(PageFixtures::enabledState())))->html;

        self::assertStringContainsString('data-nowo-ui-page="settings"', $html);
        self::assertStringContainsString('data-testid="maintenance-panel"', $html);
        self::assertStringContainsString('data-testid="maintenance-status-manual">ENABLED</span>', $html);
        self::assertStringContainsString('data-testid="maintenance-status-effective">ON</span>', $html);
        self::assertStringContainsString('Database upgrade in progress — back at 14:30.', $html);
        self::assertStringContainsString('2026-10-18 13:45:00', $html);
        self::assertStringContainsString('action="/_maintenance/enable"', $html);
        self::assertStringContainsString('action="/_maintenance/schedule"', $html);
        self::assertStringContainsString('action="/_maintenance/disable"', $html);
        self::assertStringContainsString('action="/_maintenance/clear-schedule"', $html);
        self::assertStringContainsString('action="/_maintenance/logout"', $html);
        self::assertStringContainsString('Enable maintenance', $html);
        self::assertStringContainsString('Disable maintenance', $html);
        self::assertStringContainsString('Save schedule', $html);
        self::assertStringContainsString('Clear schedule', $html);
        self::assertStringContainsString('value="token-nowo_maintenance_disable"', $html);
        // UiKit 2.2 SettingsSection::actions: one actions row per section, after the section form (not nested)
        self::assertSame(1, substr_count($html, 'data-testid="maintenance-disable"'));
        self::assertSame(1, substr_count($html, 'data-testid="maintenance-clear-schedule"'));
        self::assertLessThan(strpos($html, 'action="/_maintenance/disable"'), strpos($html, 'action="/_maintenance/enable"'));
        // each section submit button targets its own form (unique ids)
        self::assertStringContainsString('id="nowo-maintenance-enable-form"', $html);
        self::assertStringContainsString('form="nowo-maintenance-enable-form">Enable maintenance</button>', $html);
        self::assertStringContainsString('id="nowo-maintenance-schedule-form"', $html);
        self::assertStringContainsString('form="nowo-maintenance-schedule-form">Save schedule</button>', $html);
        self::assertStringNotContainsString('nowo-ui-section-form', $html);
        self::assertStringContainsString('value="token-nowo_maintenance_logout"', $html);
        self::assertSame(1, substr_count($html, 'value="token-nowo_maintenance_enable"'));
        foreach (['status', 'manual', 'schedule'] as $section) {
            self::assertStringContainsString('data-testid="maintenance-' . $section . '"', $html);
        }
    }

    /**
     * Regression (1.10.1): the panel textarea and datetime-local fields rendered with form_div_layout only (no UiKit
     * `input` slot class), unstyled and not full width on a UiKit host.
     */
    public function testPanelFieldsGetTheUiKitInputSlots(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/panel/index.html.twig', PageFixtures::index(PageFixtures::enabledState())))->html;

        // enable form message + schedule form message: full-width input slot
        self::assertSame(2, preg_match_all('#<textarea [^>]*class="nowo-ui-input nowo-ui-input--full[ "]#', $html));
        // schedule form: both datetime-local fields carry the input slot
        self::assertSame(2, preg_match_all('#<input type="datetime-local" [^>]*class="nowo-ui-input[ "]#', $html));
        self::assertStringContainsString('name="scheduled_enable_at"', $html);
        self::assertStringContainsString('name="scheduled_disable_at"', $html);
    }

    public function testLoginPasswordFieldGetsTheUiKitInputSlot(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/panel/login.html.twig', PageFixtures::login()))->html;

        self::assertMatchesRegularExpression('#<input type="password" [^>]*class="nowo-ui-input[ "]#', $html);
    }

    public function testPanelFlashesComeFromTheSessionNotFromThePeekedOneXVariable(): void
    {
        $context            = PageFixtures::index(PageFixtures::enabledState());
        $context['flashes'] = ['success' => ['panel.flash.enabled']];

        $html = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/panel/index.html.twig', $context))->html;

        self::assertStringNotContainsString('Maintenance enabled.', $html, 'The flashes partial must read (and consume) app.flashes.');
    }

    public function testPanelWithoutPasswordGateHasNoLogout(): void
    {
        $context = PageFixtures::index(PageFixtures::disabledState(), false);
        self::assertInstanceOf(SettingsPage::class, $context['page']);
        self::assertSame([], $context['page']->header->actions);

        $html = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/panel/index.html.twig', $context))->html;

        self::assertStringNotContainsString('/_maintenance/logout', $html);
        self::assertStringContainsString('data-testid="maintenance-status-manual">disabled</span>', $html);
        self::assertStringContainsString('2099-10-20 02:00:00', $html);
        self::assertStringContainsString('>cli<', $html);
    }

    public function testAreaNavIsTheUiKitTabsPartial(): void
    {
        $twig = $this->createTwig();

        $panel = $twig->render('host/page_header.html.twig', PageFixtures::index(PageFixtures::enabledState()));
        self::assertMatchesRegularExpression('/<nav class="[^"]*" aria-label="Panel"[^>]* data-testid="maintenance-panel-tabs"[^>]* data-nowo-ui-region="area-nav">/', $panel);
        self::assertMatchesRegularExpression('#href="/_maintenance/"\s+aria-current="page"\s+data-testid="maintenance-nav-panel"#', $panel);

        $history = $twig->render('host/page_header.html.twig', PageFixtures::history([]));
        self::assertMatchesRegularExpression('#href="/_maintenance/history"\s+aria-current="page"#', $history);
    }

    public function testHistoryRowsAndPagination(): void
    {
        $html = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/panel/history.html.twig', PageFixtures::history(PageFixtures::entries(7), 2, 3)))->html;

        self::assertStringContainsString('data-testid="maintenance-history"', $html);
        self::assertStringContainsString('data-nowo-ui-region="pagination"', $html);
        self::assertSame(3, substr_count($html, '<time datetime="'));
        self::assertStringContainsString('<time datetime="2026-10-18T13:45:00', $html);
        self::assertStringContainsString('>enable</span>', $html);

        $empty = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/panel/history.html.twig', PageFixtures::history([])))->html;
        self::assertStringContainsString('No history yet.', $empty);
        self::assertStringNotContainsString('data-nowo-ui-region="pagination"', $empty);
    }

    public function testLoginRendersOnTheGuestLayoutWithTheError(): void
    {
        $rendered = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/panel/login.html.twig', PageFixtures::login('Too many failed attempts.', true)));

        self::assertSame(['guest'], $rendered->layouts());
        self::assertStringContainsString('data-nowo-ui-page="auth"', $rendered->html);
        self::assertStringContainsString('Too many failed attempts. Please try again later.', $rendered->html);
        self::assertStringContainsString('type="password"', $rendered->html);
        self::assertStringContainsString('autocomplete="current-password"', $rendered->html);
        self::assertStringContainsString('value="token-nowo_maintenance_login"', $rendered->html);
        self::assertStringContainsString('Sign in', $rendered->html);
    }

    public function testPublicPageIsGuestTokensOnlyWithoutScriptsOrFlashes(): void
    {
        $rendered = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/maintenance/page.html.twig', PageFixtures::maintenance('Back at <b>14:30</b>')));
        $html     = $rendered->html;

        self::assertSame(['guest'], $rendered->layouts());
        self::assertStringContainsString('data-testid="maintenance-page"', $html);
        self::assertStringContainsString('Back at &lt;b&gt;14:30&lt;/b&gt;', $html, 'The state message is escaped.');
        self::assertStringContainsString('We&#039;re tidying up — back soon', $html);
        self::assertStringContainsString('<meta name="robots" content="noindex">', $html);
        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('<style', $rendered->blockHtml('nowo_ui_content') . $rendered->blockHtml('nowo_ui_head'), 'Only the theme tokens style (layout), no bundle CSS.');
        self::assertStringNotContainsString('fonts.googleapis', $html);
        self::assertSame('', $rendered->blockHtml('nowo_ui_flashes'), 'The 503 never reads the session.');
        self::assertSame('', $rendered->blockHtml('nowo_ui_scripts'), 'No UiKit script on the 503.');

        $default = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/maintenance/page.html.twig', PageFixtures::maintenance()))->html;
        self::assertStringContainsString('We&#039;re making a few gentle improvements.', $default);
    }

    public function testPublicPageIllustrationAndCountdownOptions(): void
    {
        $context                          = PageFixtures::maintenance('Back soon');
        $context['nowo_maintenance_page'] = ['countdown' => true, 'csp_nonce_attribute' => 'csp_nonce', 'illustration' => ['src' => '/img/503.png', 'webp' => '/img/503.webp', 'alt' => 'Closed sign', 'width' => 896, 'height' => 597]];
        $html                             = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/maintenance/page.html.twig', $context))->html;

        self::assertStringContainsString('<source type="image/webp" srcset="/img/503.webp">', $html);
        self::assertStringContainsString('<img class="nowo-maintenance-page__img" src="/img/503.png" width="896" height="597" alt="Closed sign"', $html);
        self::assertMatchesRegularExpression('#data-testid="maintenance-countdown" data-until="2099-10-18T14:30:00[^"]*">2099-10-18 14:30#', $html);
        self::assertStringContainsString("getElementById('nowo-maintenance-countdown')", $html);

        $plain = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/maintenance/page.html.twig', PageFixtures::maintenance() + ['nowo_maintenance_page' => ['countdown' => false]]))->html;
        self::assertStringNotContainsString('<script', $plain, 'no countdown, no JavaScript');
        self::assertStringNotContainsString('<figure', $plain);
    }

    public function testPanelPreviewLinkAndFormThemeOptions(): void
    {
        $context                          = PageFixtures::index(PageFixtures::enabledState());
        $context['nowo_maintenance_page'] = ['show_preview_link' => true, 'form_theme' => 'host/form_theme.html.twig'];
        $html                             = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/panel/index.html.twig', $context))->html;

        self::assertMatchesRegularExpression('#<a class="[^"]*" href="[^"]*preview" target="_blank" rel="noopener noreferrer" data-testid="maintenance-preview-link">Maintenance page preview</a>#', $html);
        self::assertStringContainsString('data-host-form-theme', $html);

        $default = $this->renderPage(new PageFixture('@NowoMaintenanceModeBundle/panel/index.html.twig', PageFixtures::index(PageFixtures::enabledState())))->html;
        self::assertStringNotContainsString('maintenance-preview-link', $default);
        self::assertStringNotContainsString('data-host-form-theme', $default);
    }

    public function testPublicPageHooksAndAuthScaffoldOverride(): void
    {
        $html = $this->renderPage(new PageFixture('host/maintenance.html.twig', PageFixtures::maintenance()))->html;
        self::assertStringContainsString('<h1>Acme is upgrading</h1>', $html);
        self::assertStringContainsString('<link rel="stylesheet" href="/css/acme-maintenance.css">', $html);
        self::assertStringContainsString('<script src="/js/acme.js" defer></script>', $html);
        self::assertStringContainsString('Be right back', $html);

        $auth = $this->renderPage(new PageFixture('host/maintenance_auth.html.twig', PageFixtures::maintenance('Back at 14:30')))->html;
        self::assertStringContainsString('data-nowo-ui-page="auth"', $auth);
        self::assertStringContainsString('Back at 14:30', $auth);
        self::assertStringContainsString('We&#039;re tidying up — back soon', $auth);
    }

    public function testHostOverrideOfADataBlockKeepsTheBundlePage(): void
    {
        $html = $this->renderPage(new PageFixture('host/history_override.html.twig', PageFixtures::history(PageFixtures::entries())))->html;

        self::assertStringContainsString('<p class="host-note">Kept 90 days.</p>', $html);
        self::assertStringContainsString('data-nowo-ui-page="list"', $html);
    }

    public function testLegacyBodyLayoutIsBridged(): void
    {
        $context                                        = PageFixtures::history([]);
        $context['page']                                = new ListPage($context['page']->header, $context['page']->columns, [], empty: $context['page']->empty, bundle: PanelView::BUNDLE, layout: PanelView::LEGACY_LAYOUT_BRIDGE);
        $context['nowo_maintenance_mode_legacy_layout'] = 'host/legacy.html.twig';

        $html = $this->createTwig()->render('@NowoMaintenanceModeBundle/panel/history.html.twig', $context);

        self::assertStringStartsWith('<legacy-layout><title>History</title>', $html);
        self::assertStringContainsString('<host-css/>', $html);
        self::assertStringContainsString('css/nowo-ui.css', $html);
        self::assertStringContainsString('data-nowo-ui-bundle="NowoMaintenanceModeBundle"', $html);
        self::assertStringContainsString('data-nowo-ui-page="list"', $html);
    }

    protected static function templatePaths(): array
    {
        return ['NowoMaintenanceModeBundle' => dirname(__DIR__, 3) . '/src/Resources/views'];
    }

    protected static function translationDirs(): array
    {
        return [dirname(__DIR__, 3) . '/src/Resources/translations'];
    }

    protected static function templatesDocument(): string
    {
        return dirname(__DIR__, 3) . '/docs/TEMPLATES.md';
    }
}
