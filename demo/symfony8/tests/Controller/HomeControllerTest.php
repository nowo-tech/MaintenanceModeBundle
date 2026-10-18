<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeControllerTest extends WebTestCase
{
    public function testHomepageIsAccessibleWhenMaintenanceOff(): void
    {
        $client = static::createClient();
        /** @var MaintenanceManager $manager */
        $manager = static::getContainer()->get(MaintenanceManager::class);
        $manager->disable('test');

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Maintenance Mode Bundle');
    }

    public function testHomepageReturns503WhenMaintenanceOn(): void
    {
        $client = static::createClient();
        /** @var MaintenanceManager $manager */
        $manager = static::getContainer()->get(MaintenanceManager::class);
        $manager->enable('Down for test', 'test');

        $client->request('GET', '/');
        self::assertResponseStatusCodeSame(503);
        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('Down for test', $html);
        // REQ-UI-003: UiKit guest layout of the demo, theme tokens, no UiKit script, no CDN from the bundle.
        self::assertSelectorExists('.nowo-ui-page--guest [data-testid="maintenance-page"]');
        self::assertStringContainsString('data-nowo-ui-host-tokens', $html);
        self::assertStringNotContainsString('/bundles/nowouikit/js/', $html);
        self::assertStringNotContainsString('fonts.googleapis.com', $html);

        $manager->disable('test');
    }

    public function testHealthRemainsAvailableDuringMaintenance(): void
    {
        $client = static::createClient();
        /** @var MaintenanceManager $manager */
        $manager = static::getContainer()->get(MaintenanceManager::class);
        $manager->enable('Down', 'test');

        $client->request('GET', '/health');
        self::assertResponseIsSuccessful();
        self::assertSame('ok', $client->getResponse()->getContent());

        $manager->disable('test');
    }

    public function testPanelLoginPageIsReachableDuringMaintenance(): void
    {
        $client = static::createClient();
        /** @var MaintenanceManager $manager */
        $manager = static::getContainer()->get(MaintenanceManager::class);
        $manager->enable('Down', 'test');

        $client->request('GET', '/_maintenance/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-nowo-ui-page="auth"] input[type="password"]');

        $manager->disable('test');
    }

    public function testPanelPagesRenderOnTheUiKitScaffoldsAfterLogin(): void
    {
        $client = static::createClient();
        /** @var MaintenanceManager $manager */
        $manager = static::getContainer()->get(MaintenanceManager::class);
        $manager->disable('test');

        $client->request('GET', '/_maintenance/');
        self::assertResponseRedirects('/_maintenance/login');

        $crawler = $client->request('GET', '/_maintenance/login');
        $client->submit($crawler->filter('[data-nowo-ui-page="auth"] form')->form(['password' => 'maintenance']));
        self::assertResponseRedirects();

        $client->request('GET', '/_maintenance/');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-nowo-ui-page="settings"] [data-testid="maintenance-status"]');
        self::assertSelectorExists('[data-testid="maintenance-panel-tabs"]');
        self::assertSelectorTextContains('[data-testid="maintenance-status-manual"]', 'disabled');

        $client->request('GET', '/_maintenance/history');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-nowo-ui-page="list"][data-testid="maintenance-history"]');
    }
}
