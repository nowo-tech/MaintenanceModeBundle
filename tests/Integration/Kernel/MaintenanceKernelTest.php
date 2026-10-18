<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Integration\Kernel;

use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function sys_get_temp_dir;
use function uniqid;

use const PASSWORD_BCRYPT;

/**
 * Functional checks in a real kernel: the public 503 renders on the UiKit guest layout without session, database,
 * CDN or UiKit scripts; the panel pages render on the UiKit scaffolds.
 */
final class MaintenanceKernelTest extends TestCase
{
    /** @var list<MaintenanceTestKernel> */
    private array $kernels = [];

    protected function tearDown(): void
    {
        foreach ($this->kernels as $kernel) {
            $dir = $kernel->getProjectDir();
            $kernel->shutdown();
            (new Filesystem())->remove($dir);
        }
        $this->kernels = [];
    }

    public function testPublicMaintenancePageIsAFastGuestPage(): void
    {
        $kernel = $this->boot();
        $this->manager($kernel)->enable('Database upgrade — back at 14:30.', 'test');

        $request  = Request::create('/');
        $response = $kernel->handle($request);
        $html     = (string) $response->getContent();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertSame('3600', $response->headers->get('Retry-After'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringStartsWith('<!DOCTYPE html>', $html);
        self::assertStringContainsString('nowo-ui-page--guest', $html);
        self::assertStringContainsString('data-testid="maintenance-page"', $html);
        self::assertStringContainsString('Database upgrade — back at 14:30.', $html);
        self::assertStringContainsString('<title>A short pause</title>', $html);
        self::assertStringContainsString('/bundles/nowouikit/css/nowo-ui.css', $html);
        self::assertStringNotContainsString('<script src', $html, 'No UiKit (or other) script file on the 503.');
        self::assertDoesNotMatchRegularExpression('#(href|src)="(https?:)?//#', $html, 'No CDN asset.');
        self::assertSame([], $response->headers->getCookies(), 'No session / bypass cookie.');
        self::assertFalse($request->hasSession() && $request->getSession()->isStarted(), 'The 503 never starts the session.');

        $json = $kernel->handle(Request::create('/', server: ['HTTP_ACCEPT' => 'application/json']));
        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $json->getStatusCode());
        self::assertStringContainsString('"status":"maintenance"', (string) $json->getContent());
    }

    public function testBrokenHostTemplateFallsBackToTheBuiltInPage(): void
    {
        $kernel = $this->boot(['templates' => ['page' => '@NowoMaintenanceModeBundle/does-not-exist.html.twig']]);
        $this->manager($kernel)->enable(null, 'test');

        $response = $kernel->handle(Request::create('/'));

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertStringContainsString('<h1>Maintenance</h1>', (string) $response->getContent());
    }

    public function testSiteAndPanelWhileMaintenanceIsOff(): void
    {
        $kernel = $this->boot();

        self::assertSame('<p>home</p>', $kernel->handle(Request::create('/'))->getContent());

        $panel = $kernel->handle(Request::create('/_maintenance/'));
        self::assertSame(Response::HTTP_OK, $panel->getStatusCode());
        self::assertStringContainsString('data-nowo-ui-page="settings"', (string) $panel->getContent());
        self::assertStringContainsString('data-testid="maintenance-panel-tabs"', (string) $panel->getContent());
        self::assertStringContainsString('name="_token"', (string) $panel->getContent());

        $this->manager($kernel)->enable('Going down', 'test');
        $history = $kernel->handle(Request::create('/_maintenance/history'));
        self::assertSame(Response::HTTP_OK, $history->getStatusCode(), 'The panel stays reachable during maintenance.');
        self::assertStringContainsString('data-nowo-ui-page="list"', (string) $history->getContent());
        self::assertStringContainsString('Going down', (string) $history->getContent());
    }

    public function testPasswordLoginOnTheGuestLayout(): void
    {
        $kernel = $this->boot(['security' => ['allow_unauthenticated' => true, 'password_protection' => true, 'password_hash' => password_hash('secret', PASSWORD_BCRYPT)]]);

        $redirect = $kernel->handle(Request::create('/_maintenance/'));
        self::assertSame('/_maintenance/login', $redirect->headers->get('Location'));

        $login = $kernel->handle(Request::create('/_maintenance/login'));
        self::assertSame(Response::HTTP_OK, $login->getStatusCode());
        self::assertStringContainsString('nowo-ui-page--guest', (string) $login->getContent());
        self::assertStringContainsString('data-nowo-ui-page="auth"', (string) $login->getContent());
        self::assertStringContainsString('type="password"', (string) $login->getContent());
    }

    /**
     * @param array<string, mixed> $config
     */
    private function boot(array $config = []): MaintenanceTestKernel
    {
        $kernel = new MaintenanceTestKernel(sys_get_temp_dir() . '/nowo-mm-kernel-' . uniqid('', true), $config);
        $kernel->boot();
        $this->kernels[] = $kernel;

        return $kernel;
    }

    private function manager(MaintenanceTestKernel $kernel): MaintenanceManager
    {
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $manager = $container->get(MaintenanceManager::class);
        self::assertInstanceOf(MaintenanceManager::class, $manager);

        return $manager;
    }
}
