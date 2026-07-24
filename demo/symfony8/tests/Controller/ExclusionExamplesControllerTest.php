<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ExclusionExamplesControllerTest extends WebTestCase
{
    /**
     * @return iterable<string, array{0: string}>
     */
    public static function excludedEndpointsProvider(): iterable
    {
        yield 'exact path' => ['/health'];
        yield 'route name' => ['/status'];
        yield 'path prefix' => ['/api/ops/ping'];
        yield 'glob pattern' => ['/internal-check'];
        yield 'regex pattern' => ['/ops/ready'];
        yield 'examples prefix' => ['/examples/bypass'];
    }

    #[DataProvider('excludedEndpointsProvider')]
    public function testExcludedEndpointsStayAvailableDuringMaintenance(string $path): void
    {
        $client = static::createClient();
        /** @var MaintenanceManager $manager */
        $manager = static::getContainer()->get(MaintenanceManager::class);
        $manager->enable('Down for exclusion demo', 'test');

        $client->request('GET', $path);
        self::assertResponseIsSuccessful();

        $manager->disable('test');
    }

    public function testHomepageIsStillBlocked(): void
    {
        $client = static::createClient();
        /** @var MaintenanceManager $manager */
        $manager = static::getContainer()->get(MaintenanceManager::class);
        $manager->enable('Down', 'test');

        $client->request('GET', '/');
        self::assertResponseStatusCodeSame(503);

        $manager->disable('test');
    }

    public function testBypassGuideMentionsLoginToggle(): void
    {
        $client = static::createClient();
        $client->request('GET', '/examples/bypass');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bypass the 503 page');
        self::assertStringContainsString('password_protection', (string) $client->getResponse()->getContent());
    }
}
