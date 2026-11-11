<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Integration\Kernel;

use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\ContextActorResolver;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\IpAllowListContextGate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function gc_collect_cycles;
use function memory_get_usage;
use function sys_get_temp_dir;
use function uniqid;

/**
 * DDD host path of REQ-ARCH-001 rule 3: a kernel whose panel gate and actor resolver are host services that implement
 * ONLY the framework-free ports (`MaintenancePanelAccessGateInterface`, `MaintenanceActorResolverInterface`; no
 * `Request`). The bundle has no Doctrine and no host entity, so there is no EntityManager or mapping to configure.
 *
 * One kernel serves alternating visitors (kernel reset false, no services resetter between requests): each request
 * gets its own access context, so a granted visitor never leaks its decision to the next one.
 */
final class DddHostAccessPortsKernelTest extends TestCase
{
    private const ITERATIONS = 300;

    private const MEMORY_MARGIN = 4 * 1024 * 1024;

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

    public function testPanelUsesTheHostContextGateWithoutRequestPorts(): void
    {
        $kernel = $this->bootKernel();

        $granted = $kernel->handle(Request::create('/_maintenance/', server: ['REMOTE_ADDR' => IpAllowListContextGate::OPS_IP]));
        self::assertSame(Response::HTTP_OK, $granted->getStatusCode());

        $denied = $kernel->handle(Request::create('/_maintenance/', server: ['REMOTE_ADDR' => '198.51.100.8']));
        self::assertInstanceOf(RedirectResponse::class, $denied);
        self::assertSame('/_maintenance/login', $denied->getTargetUrl());

        $forwarded = $kernel->handle(Request::create('/_maintenance/', server: [
            'REMOTE_ADDR'          => '198.51.100.8',
            'HTTP_X_FORWARDED_FOR' => IpAllowListContextGate::OPS_IP,
        ]));
        self::assertSame(Response::HTTP_FOUND, $forwarded->getStatusCode(), 'An untrusted X-Forwarded-For does not reach the gate.');
    }

    public function testAlternatingVisitorsInOneKernelKeepTheirOwnDecisionAndFlatMemory(): void
    {
        $kernel   = $this->bootKernel();
        $baseline = 0;

        for ($i = 0; $i < self::ITERATIONS; ++$i) {
            $ops      = $i % 2 === 0;
            $request  = Request::create('/_maintenance/', server: ['REMOTE_ADDR' => $ops ? IpAllowListContextGate::OPS_IP : '198.51.100.' . (10 + $i % 50)]);
            $response = $kernel->handle($request);
            self::assertSame($ops ? Response::HTTP_OK : Response::HTTP_FOUND, $response->getStatusCode(), 'iteration ' . $i);
            $kernel->terminate($request, $response);

            if ($i === 49) {
                gc_collect_cycles();
                $baseline = memory_get_usage();
            }
        }

        gc_collect_cycles();
        self::assertLessThan($baseline + self::MEMORY_MARGIN, memory_get_usage());
    }

    private function bootKernel(): MaintenanceTestKernel
    {
        $dir          = sys_get_temp_dir() . '/nowo-mm-ddd-' . uniqid('', true);
        $this->kernel = new MaintenanceTestKernel($dir, [
            'security' => [
                'allow_unauthenticated' => true,
                'access_gate'           => 'app.maintenance_gate',
                'actor_resolver'        => 'app.maintenance_actor',
            ],
        ], true, static function (ContainerConfigurator $container): void {
            $services = $container->services();
            $services->set('app.maintenance_gate', IpAllowListContextGate::class);
            $services->set('app.maintenance_actor', ContextActorResolver::class);
        });
        $this->kernel->boot();

        return $this->kernel;
    }
}
