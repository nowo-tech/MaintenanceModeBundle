<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Integration\Kernel;

use Nowo\MaintenanceModeBundle\Event\MaintenanceDisabledEvent;
use Nowo\MaintenanceModeBundle\Event\MaintenanceEnabledEvent;
use Nowo\MaintenanceModeBundle\Security\ActorResolverInterface;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManagerInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\FixedActorResolver;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\InMemoryMaintenanceHistoryStorage;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\InMemoryMaintenanceStateStorage;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\MaintenanceEventProbe;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function sys_get_temp_dir;
use function uniqid;

/**
 * DDD host path of the architecture bar: a kernel whose state storage, history storage and actor resolver are host
 * services (set through `storage.state_storage`, `storage.history_storage`, `security.actor_resolver`). The standard
 * host path (default filesystem storage) is covered by {@see MaintenanceKernelTest}.
 */
final class HostPortsKernelTest extends TestCase
{
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

    public function testPortAliasesResolveToHostServices(): void
    {
        $container = $this->testContainer();

        self::assertSame($container->get('app.maintenance_state'), $container->get(MaintenanceStateStorageInterface::class));
        self::assertSame($container->get('app.maintenance_history'), $container->get(MaintenanceHistoryStorageInterface::class));
        self::assertInstanceOf(FixedActorResolver::class, $container->get(ActorResolverInterface::class));
    }

    public function testManagerWritesThroughHostStorageAndDispatchesAfterSave(): void
    {
        $container = $this->testContainer();
        $manager   = $container->get(MaintenanceManagerInterface::class);
        self::assertInstanceOf(MaintenanceManagerInterface::class, $manager);
        $state = $container->get('app.maintenance_state');
        self::assertInstanceOf(InMemoryMaintenanceStateStorage::class, $state);
        $history = $container->get('app.maintenance_history');
        self::assertInstanceOf(InMemoryMaintenanceHistoryStorage::class, $history);
        $probe = $container->get(MaintenanceEventProbe::class);
        self::assertInstanceOf(MaintenanceEventProbe::class, $probe);

        $manager->enable('Host storage window.', 'host-user-42');

        self::assertTrue($state->load()->isEnabled());
        self::assertSame('host-user-42', $state->load()->getUpdatedBy());
        self::assertFileDoesNotExist($this->kernel?->getProjectDir() . '/state.json', 'The filesystem default is not used.');
        $entries = $history->list();
        self::assertCount(1, $entries);
        self::assertSame('enable', $entries[0]->getAction());
        self::assertSame('host-user-42', $entries[0]->getActor());
        self::assertSame([['event' => 'enabled', 'actor' => 'host-user-42', 'storedEnabled' => true]], $probe->seen, 'Event dispatched after save().');

        $response = $this->kernel?->handle(Request::create('/'));
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertStringContainsString('Host storage window.', (string) $response->getContent());

        $manager->disable('host-user-42');

        self::assertFalse($state->load()->isEnabled());
        self::assertSame([
            ['event' => 'enabled', 'actor' => 'host-user-42', 'storedEnabled' => true],
            ['event' => 'disabled', 'actor' => 'host-user-42', 'storedEnabled' => false],
        ], $probe->seen);
        $response = $this->kernel?->handle(Request::create('/'));
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    private function testContainer(): ContainerInterface
    {
        $dir          = sys_get_temp_dir() . '/nowo-mm-host-' . uniqid('', true);
        $this->kernel = new MaintenanceTestKernel($dir, [
            'security' => [
                'allow_unauthenticated' => true,
                'password_protection'   => false,
                'actor_resolver'        => 'app.actor_resolver',
            ],
            'storage' => [
                'state_file'      => $dir . '/state.json',
                'history_file'    => $dir . '/history.jsonl',
                'state_storage'   => 'app.maintenance_state',
                'history_storage' => 'app.maintenance_history',
            ],
        ], true, static function (ContainerConfigurator $container): void {
            $services = $container->services();
            $services->set('app.maintenance_state', InMemoryMaintenanceStateStorage::class)->public();
            $services->set('app.maintenance_history', InMemoryMaintenanceHistoryStorage::class)->public();
            $services->set('app.actor_resolver', FixedActorResolver::class)->public();
            $services->set(MaintenanceEventProbe::class)
                ->args([service('app.maintenance_state')])
                ->public()
                ->tag('kernel.event_listener', ['event' => MaintenanceEnabledEvent::class, 'method' => 'onEnabled'])
                ->tag('kernel.event_listener', ['event' => MaintenanceDisabledEvent::class, 'method' => 'onDisabled']);
        });
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);

        return $container;
    }
}
