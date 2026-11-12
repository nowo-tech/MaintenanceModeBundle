<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Integration\Kernel;

use Closure;
use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Nowo\MaintenanceModeBundle\DependencyInjection\Compiler\AccessPortsPass;
use Nowo\MaintenanceModeBundle\Security\ActorResolverInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceActorResolverInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenancePanelAccessGateInterface;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\ContextActorResolver;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\DualPortActorResolver;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\DualPortGate;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\FixedActorResolver;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\IpAllowListContextGate;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\OpsOnlyDecoratingGate;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\PrefixingDecoratingActorResolver;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\RequestOnlyGate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function sys_get_temp_dir;
use function uniqid;

/**
 * 1.17.1: `lint:container` (which adds Symfony's `CheckAliasValidityPass`) passes for a configured
 * `security.access_gate` / `security.actor_resolver` on the framework-free port only, on the deprecated port only, on
 * both, and with a host `#[AsDecorator]` on the new interface; each alias exists only when its service implements it,
 * and the panel keeps the port {@see AccessPortsPass} chose.
 */
final class PortAliasValidityKernelTest extends TestCase
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

    /**
     * @return iterable<string, array{string, class-string, (Closure(ContainerConfigurator): void)|null, string, class-string, class-string, class-string, bool, bool}>
     */
    public static function hostPorts(): iterable
    {
        $gate  = [MaintenanceAccessGateInterface::class, MaintenancePanelAccessGateInterface::class];
        $actor = [ActorResolverInterface::class, MaintenanceActorResolverInterface::class];

        yield 'gate on the new port only' => ['access_gate', IpAllowListContextGate::class, null, 'accessGate', IpAllowListContextGate::class, ...$gate, false, true];
        yield 'gate on the deprecated port only' => ['access_gate', RequestOnlyGate::class, null, 'accessGate', RequestOnlyGate::class, ...$gate, true, false];
        yield 'gate on both ports' => ['access_gate', DualPortGate::class, null, 'accessGate', DualPortGate::class, ...$gate, true, true];
        yield 'decorator on the new gate port' => ['access_gate', IpAllowListContextGate::class, self::decorate(OpsOnlyDecoratingGate::class), 'accessGate', OpsOnlyDecoratingGate::class, ...$gate, false, true];
        yield 'resolver on the new port only' => ['actor_resolver', ContextActorResolver::class, null, 'actorResolver', ContextActorResolver::class, ...$actor, false, true];
        yield 'resolver on the deprecated port only' => ['actor_resolver', FixedActorResolver::class, null, 'actorResolver', FixedActorResolver::class, ...$actor, true, false];
        yield 'resolver on both ports' => ['actor_resolver', DualPortActorResolver::class, null, 'actorResolver', DualPortActorResolver::class, ...$actor, true, true];
        yield 'decorator on the new resolver port' => ['actor_resolver', ContextActorResolver::class, self::decorate(PrefixingDecoratingActorResolver::class), 'actorResolver', PrefixingDecoratingActorResolver::class, ...$actor, false, true];
    }

    /**
     * @param class-string $service
     * @param (Closure(ContainerConfigurator): void)|null $extra
     * @param class-string $expectedPanelPort
     * @param class-string $legacy
     * @param class-string $new
     */
    #[DataProvider('hostPorts')]
    public function testLintContainerPasses(string $key, string $service, ?Closure $extra, string $property, string $expectedPanelPort, string $legacy, string $new, bool $hasLegacy, bool $hasNew): void
    {
        $this->kernel = new MaintenanceTestKernel(sys_get_temp_dir() . '/nowo-mm-alias-' . uniqid('', true), [
            'security' => ['allow_unauthenticated' => true, $key => $service],
        ], true, static function (ContainerConfigurator $container) use ($service, $extra): void {
            $container->services()->set($service);
            if ($extra instanceof Closure) {
                $extra($container);
            }
        });
        $this->kernel->boot();

        $application = new Application($this->kernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('lint:container'));
        self::assertSame(0, $tester->execute([]), $tester->getDisplay());
        self::assertStringContainsString('linted successfully', $tester->getDisplay());

        /** @var ContainerInterface $services */
        $services = $this->kernel->getContainer()->get('test.service_container');
        self::assertSame($hasLegacy, $services->has($legacy), $legacy);
        self::assertSame($hasNew, $services->has($new), $new);

        $controller = $services->get(MaintenancePanelController::class);
        self::assertInstanceOf(MaintenancePanelController::class, $controller);
        self::assertInstanceOf($expectedPanelPort, (new ReflectionProperty($controller, $property))->getValue($controller));
    }

    /**
     * @param class-string $decorator
     *
     * @return Closure(ContainerConfigurator): void
     */
    private static function decorate(string $decorator): Closure
    {
        return static function (ContainerConfigurator $container) use ($decorator): void {
            $container->services()->defaults()->autowire()->autoconfigure()->set($decorator);
        };
    }
}
