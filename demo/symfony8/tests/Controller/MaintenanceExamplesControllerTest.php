<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MaintenanceExamplesControllerTest extends WebTestCase
{
    public function testExamplesIndexIsReachable(): void
    {
        $client = static::createClient();
        $client->request('GET', '/examples');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Maintenance page examples');
        self::assertSelectorExists('a[href*="bootstrap-calm"]');
    }

    public function testExamplePreviewRendersAndStaysAvailableDuringMaintenance(): void
    {
        $client = static::createClient();
        /** @var MaintenanceManager $manager */
        $manager = static::getContainer()->get(MaintenanceManager::class);
        $manager->enable('Down for test', 'test');

        $client->request('GET', '/examples/bootstrap-calm', ['_locale' => 'es']);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('momento tranquilo', (string) $client->getResponse()->getContent());

        $manager->disable('test');
    }

    public function testUnknownExampleReturns404(): void
    {
        $client = static::createClient();
        $client->request('GET', '/examples/does-not-exist');
        self::assertResponseStatusCodeSame(404);
    }
}
