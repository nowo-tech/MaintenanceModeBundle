<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\DependencyInjection\Compiler;

use LogicException;
use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Nowo\MaintenanceModeBundle\DependencyInjection\Compiler\AccessPortsPass;
use Nowo\MaintenanceModeBundle\Security\ActorResolverInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceActorResolverInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenancePanelAccessGateInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class AccessPortsPassTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function wirings(): iterable
    {
        yield 'both on the default' => ['default', 'default', 'default'];
        yield 'both on one host service' => ['host', 'host', 'host'];
        yield 'only the new alias changed' => ['default', 'host', 'host'];
        yield 'only the deprecated alias changed' => ['host', 'default', 'host'];
    }

    #[DataProvider('wirings')]
    public function testThePanelGetsThePortTheHostChose(string $legacy, string $new, string $expected): void
    {
        $container = $this->container($legacy, $new);

        (new AccessPortsPass())->process($container);

        $arguments = $container->getDefinition(MaintenancePanelController::class)->getArguments();
        self::assertEquals(new Reference($expected), $arguments['$accessGate']);
        self::assertEquals(new Reference($expected), $arguments['$actorResolver']);
        self::assertFalse($container->hasParameter(AccessPortsPass::GATE_PARAMETER));
        self::assertFalse($container->hasParameter(AccessPortsPass::ACTOR_PARAMETER));
    }

    public function testAliasChainsAreFollowed(): void
    {
        $container = $this->container('default', 'chained');
        $container->setAlias('chained', 'host');

        (new AccessPortsPass())->process($container);

        self::assertEquals(new Reference('host'), $container->getDefinition(MaintenancePanelController::class)->getArgument('$accessGate'));
    }

    public function testTwoDifferentHostServicesFailTheBuild(): void
    {
        $container = $this->container('host', 'other');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('aliased to "host"');
        (new AccessPortsPass())->process($container);
    }

    public function testWithoutThePanelOnlyTheParametersAreDropped(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(AccessPortsPass::GATE_PARAMETER, 'default');
        $container->setParameter(AccessPortsPass::ACTOR_PARAMETER, ['not a service id']);

        (new AccessPortsPass())->process($container);

        self::assertFalse($container->hasParameter(AccessPortsPass::GATE_PARAMETER));
        self::assertFalse($container->hasParameter(AccessPortsPass::ACTOR_PARAMETER));
    }

    public function testWithoutTheParametersTheArgumentsStay(): void
    {
        $container = new ContainerBuilder();
        $container->register(MaintenancePanelController::class)
            ->setArgument('$accessGate', new Reference(MaintenancePanelAccessGateInterface::class));

        (new AccessPortsPass())->process($container);

        self::assertEquals(new Reference(MaintenancePanelAccessGateInterface::class), $container->getDefinition(MaintenancePanelController::class)->getArgument('$accessGate'));
    }

    private function container(string $legacy, string $new): ContainerBuilder
    {
        $container = new ContainerBuilder();
        foreach (['default', 'host', 'other'] as $id) {
            $container->register($id);
        }
        $container->register(MaintenancePanelController::class)
            ->setArgument('$accessGate', new Reference(MaintenancePanelAccessGateInterface::class))
            ->setArgument('$actorResolver', new Reference(MaintenanceActorResolverInterface::class));
        $container->setAlias(MaintenanceAccessGateInterface::class, $legacy);
        $container->setAlias(MaintenancePanelAccessGateInterface::class, $new);
        $container->setAlias(ActorResolverInterface::class, $legacy);
        $container->setAlias(MaintenanceActorResolverInterface::class, $new);
        $container->setParameter(AccessPortsPass::GATE_PARAMETER, 'default');
        $container->setParameter(AccessPortsPass::ACTOR_PARAMETER, 'default');

        return $container;
    }
}
