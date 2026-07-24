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
        self::assertStringContainsString('Down for test', (string) $client->getResponse()->getContent());

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

        $manager->disable('test');
    }
}
