<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Integration\Worker;

use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Tests\Integration\Kernel\MaintenanceTestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernel;

use function gc_collect_cycles;
use function memory_get_usage;
use function sprintf;
use function sys_get_temp_dir;
use function uniqid;

/**
 * REQ-RUNTIME-002: ONE booted kernel, `services_resetter` / `kernel.reset` never called (requests go through the `http_kernel` service, not `Kernel::handle()`, which resets services after a terminate()) (FrankenPHP worker, kernel
 * reset false). N iterations alternate two visitors through `http_kernel` `handle()` + `terminate()` while
 * maintenance is on: visitor A carries the QA bypass token (own page, own bypass cookie), visitor B does not (503,
 * no cookie, no trace of A). A third request type (A again, now with the cookie it was given) proves the cookie decision is
 * read per request. Memory growth after warm-up must stay below 4 MiB.
 */
final class WorkerKeptAliveKernelTest extends TestCase
{
    private const ITERATIONS = 1200;
    private const WARM_UP    = 100;
    private const MARGIN     = 4 * 1024 * 1024;
    private const TOKEN      = 'qa-bypass-token';

    private ?MaintenanceTestKernel $kernel = null;

    protected function tearDown(): void
    {
        if ($this->kernel instanceof MaintenanceTestKernel) {
            $dir = $this->kernel->getProjectDir();
            $this->kernel->shutdown();
            (new Filesystem())->remove($dir);
        }
        $this->kernel = null;
    }

    public function testAlternatingVisitorsInOneKernelLeakNothingAndKeepMemoryFlat(): void
    {
        $dir          = sys_get_temp_dir() . '/nowo-mm-worker-' . uniqid('', true);
        $this->kernel = new MaintenanceTestKernel($dir, [
            'security' => ['allow_unauthenticated' => true, 'password_protection' => false, 'bypass_token' => self::TOKEN],
        ]);
        $this->kernel->boot();
        // The `http_kernel` service directly, never `Kernel::handle()`: after a terminate() that one runs the services resetter.
        $httpKernel = $this->kernel->getContainer()->get('http_kernel');
        self::assertInstanceOf(HttpKernel::class, $httpKernel);
        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $manager = $container->get(MaintenanceManager::class);
        self::assertInstanceOf(MaintenanceManager::class, $manager);
        $manager->enable('Back soon', 'worker-test');

        $baseline = 0;
        $cookie   = '';
        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            if ($i === self::WARM_UP) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }

            $kind = $i % 4;
            if ($kind === 0) {
                // Visitor A, first visit: bypass token in the query.
                $request = Request::create('/', 'GET', ['maintenance_bypass' => self::TOKEN], [], [], ['REMOTE_ADDR' => '203.0.113.1']);
            } elseif ($kind === 2) {
                // Visitor A, next visit: only the cookie.
                $request = Request::create('/', 'GET', [], ['nowo_maintenance_bypass' => $cookie], [], ['REMOTE_ADDR' => '203.0.113.1']);
            } else {
                // Visitor B: no token, no cookie.
                $request = Request::create('/', 'GET', [], [], [], ['REMOTE_ADDR' => '198.51.100.' . (10 + $i % 50)]);
            }

            $response = $httpKernel->handle($request);
            $content  = (string) $response->getContent();
            $context  = sprintf('iteration %d', $i);

            if ($kind === 1 || $kind === 3) {
                self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode(), $context);
                self::assertStringContainsString('Back soon', $content, $context);
                self::assertSame([], $response->headers->getCookies(), $context . ': visitor B got a cookie');
                self::assertFalse($request->attributes->has('_nowo_maintenance_bypass_cookie'), $context);
            } else {
                self::assertSame(Response::HTTP_OK, $response->getStatusCode(), $context);
                self::assertSame('<p>home</p>', $content, $context);
                self::assertStringNotContainsString('Back soon', $content, $context);
                if ($kind === 0) {
                    self::assertCount(1, $response->headers->getCookies(), $context . ': bypass cookie missing');
                    $cookie = $response->headers->getCookies()[0]->getValue();
                    self::assertNotSame('', $cookie, $context);
                }
            }
            $httpKernel->terminate($request, $response);
        }

        gc_collect_cycles();
        $growth = memory_get_usage() - $baseline;
        self::assertLessThan(self::MARGIN, $growth, sprintf('memory grew by %d bytes over %d iterations', $growth, self::ITERATIONS - self::WARM_UP));
    }
}
