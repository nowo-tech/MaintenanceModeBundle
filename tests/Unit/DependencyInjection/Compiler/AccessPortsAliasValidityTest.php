<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\MaintenanceModeBundle\DependencyInjection\Compiler\AccessPortsPass;
use Nowo\MaintenanceModeBundle\Security\ActorResolverInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceActorResolverInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenancePanelAccessGateInterface;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\ContextActorResolver;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\RequestOnlyGate;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * 1.17.1: {@see AccessPortsPass} keeps each port alias only when its service implements that interface.
 */
final class AccessPortsAliasValidityTest extends TestCase
{
    public function testDropsTheAliasOfThePortTheServiceDoesNotImplementEvenWithoutThePanel(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('app.gate_class', RequestOnlyGate::class);
        $container->register('app.gate', '%app.gate_class%');
        $container->setDefinition(ContextActorResolver::class, new Definition());
        $container->setAlias(MaintenanceAccessGateInterface::class, 'app.gate');
        $container->setAlias(MaintenancePanelAccessGateInterface::class, 'app.gate');
        $container->setAlias('app.actor', ContextActorResolver::class);
        $container->setAlias(ActorResolverInterface::class, 'app.actor');
        $container->setAlias(MaintenanceActorResolverInterface::class, 'app.actor');

        (new AccessPortsPass())->process($container);

        self::assertTrue($container->hasAlias(MaintenanceAccessGateInterface::class));
        self::assertFalse($container->hasAlias(MaintenancePanelAccessGateInterface::class));
        self::assertFalse($container->hasAlias(ActorResolverInterface::class));
        self::assertTrue($container->hasAlias(MaintenanceActorResolverInterface::class));
    }

    public function testKeepsTheAliasWhenTheClassCannotBeKnown(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.factory_gate', RequestOnlyGate::class)->setFactory([RequestOnlyGate::class, 'create']);
        $container->register('app.synthetic_gate')->setSynthetic(true);
        $container->register('app.virtual_actor', 'App\\Missing\\Actor');
        $container->setAlias(MaintenancePanelAccessGateInterface::class, 'app.factory_gate');
        $container->setAlias(MaintenanceAccessGateInterface::class, 'app.synthetic_gate');
        $container->setAlias(ActorResolverInterface::class, 'app.virtual_actor');
        $container->setAlias(MaintenanceActorResolverInterface::class, 'app.undefined');

        (new AccessPortsPass())->process($container);

        self::assertTrue($container->hasAlias(MaintenancePanelAccessGateInterface::class));
        self::assertTrue($container->hasAlias(MaintenanceAccessGateInterface::class));
        self::assertTrue($container->hasAlias(ActorResolverInterface::class));
        self::assertTrue($container->hasAlias(MaintenanceActorResolverInterface::class));
    }

    public function testAServiceOnNeitherPortFailsTheBuild(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.gate', stdClass::class);
        $container->setAlias(MaintenancePanelAccessGateInterface::class, 'app.gate');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The maintenance panel service "app.gate" (stdClass) must implement "' . MaintenancePanelAccessGateInterface::class . '"');

        (new AccessPortsPass())->process($container);
    }
}
