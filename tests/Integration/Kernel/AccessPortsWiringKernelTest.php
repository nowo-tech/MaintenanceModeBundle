<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Integration\Kernel;

use Closure;
use LogicException;
use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Nowo\MaintenanceModeBundle\Security\ActorResolverInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceActorResolverInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenancePanelAccessGateInterface;
use Nowo\MaintenanceModeBundle\Security\PasswordMaintenanceAccessGate;
use Nowo\MaintenanceModeBundle\Security\StaticActorResolver;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\ContextActorResolver;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\FixedActorResolver;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\IpAllowListContextGate;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\OpsOnlyDecoratingGate;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use function sys_get_temp_dir;
use function uniqid;

/**
 * Audit MK-S-M1 / MK-S-L4: a host that aliases or decorates ONLY the framework-free port must be the one the panel
 * asks. The bundle default gate here has no password hash, so it grants everyone: if the panel ignored the host
 * port, every visitor would reach the panel.
 */
final class AccessPortsWiringKernelTest extends TestCase
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

    public function testTheDefaultGateGrantsEveryoneWithoutAPasswordHash(): void
    {
        $kernel = $this->boot(null);

        self::assertInstanceOf(PasswordMaintenanceAccessGate::class, $this->panelPort($kernel, 'accessGate'));
        self::assertInstanceOf(StaticActorResolver::class, $this->panelPort($kernel, 'actorResolver'));
        self::assertSame(Response::HTTP_OK, $kernel->handle($this->visit('198.51.100.8'))->getStatusCode(), 'baseline of the tests below');
    }

    public function testAHostAliasOnTheNewInterfaceOnlyIsThePanelGateAndActor(): void
    {
        $kernel = $this->boot(static function (ContainerConfigurator $container): void {
            $services = $container->services();
            $services->set('app.gate', IpAllowListContextGate::class);
            $services->set('app.actor', ContextActorResolver::class);
            $services->alias(MaintenancePanelAccessGateInterface::class, 'app.gate');
            $services->alias(MaintenanceActorResolverInterface::class, 'app.actor');
        });

        self::assertInstanceOf(IpAllowListContextGate::class, $this->panelPort($kernel, 'accessGate'));
        self::assertInstanceOf(ContextActorResolver::class, $this->panelPort($kernel, 'actorResolver'));
        $denied = $kernel->handle($this->visit('198.51.100.8'));
        self::assertInstanceOf(RedirectResponse::class, $denied);
        self::assertSame('/_maintenance/login', $denied->getTargetUrl());
        self::assertSame(Response::HTTP_OK, $kernel->handle($this->visit(IpAllowListContextGate::OPS_IP))->getStatusCode());
    }

    public function testAnAsDecoratorOnTheNewInterfaceOnlyIsThePanelGate(): void
    {
        $kernel = $this->boot(static function (ContainerConfigurator $container): void {
            $container->services()->defaults()->autowire()->autoconfigure()->set(OpsOnlyDecoratingGate::class);
        });

        self::assertInstanceOf(OpsOnlyDecoratingGate::class, $this->panelPort($kernel, 'accessGate'));
        self::assertInstanceOf(RedirectResponse::class, $kernel->handle($this->visit('198.51.100.8')), 'the decorator narrows the default grant');
        self::assertSame(Response::HTTP_OK, $kernel->handle($this->visit(IpAllowListContextGate::OPS_IP))->getStatusCode());
    }

    public function testAHostAliasOnTheDeprecatedInterfaceOnlyStillWins(): void
    {
        $kernel = $this->boot(static function (ContainerConfigurator $container): void {
            $services = $container->services();
            $services->set('app.actor', FixedActorResolver::class);
            $services->alias(ActorResolverInterface::class, 'app.actor');
        });

        self::assertInstanceOf(FixedActorResolver::class, $this->panelPort($kernel, 'actorResolver'));
    }

    public function testTwoDifferentHostServicesOnTheTwoGateAliasesFailTheBuild(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The maintenance panel cannot choose between "' . MaintenanceAccessGateInterface::class . '"');

        $this->boot(static function (ContainerConfigurator $container): void {
            $services = $container->services();
            $services->set('app.gate', IpAllowListContextGate::class);
            $services->set('app.other_gate', IpAllowListContextGate::class);
            $services->alias(MaintenancePanelAccessGateInterface::class, 'app.gate');
            $services->alias(MaintenanceAccessGateInterface::class, 'app.other_gate');
        });
    }

    /**
     * @param (Closure(ContainerConfigurator): void)|null $host
     */
    private function boot(?Closure $host): MaintenanceTestKernel
    {
        $this->kernel = new MaintenanceTestKernel(sys_get_temp_dir() . '/nowo-mm-ports-' . uniqid('', true), [
            'security' => ['allow_unauthenticated' => true],
        ], true, $host);
        $this->kernel->boot();

        return $this->kernel;
    }

    private function visit(string $ip): Request
    {
        return Request::create('/_maintenance/', server: ['REMOTE_ADDR' => $ip]);
    }

    private function panelPort(MaintenanceTestKernel $kernel, string $property): object
    {
        $controller = $kernel->getContainer()->get(MaintenancePanelController::class);
        self::assertInstanceOf(MaintenancePanelController::class, $controller);
        $value = (new ReflectionProperty($controller, $property))->getValue($controller);
        self::assertIsObject($value);

        return $value;
    }
}
