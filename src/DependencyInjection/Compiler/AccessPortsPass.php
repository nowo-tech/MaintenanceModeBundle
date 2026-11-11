<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\DependencyInjection\Compiler;

use LogicException;
use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Nowo\MaintenanceModeBundle\Security\ActorResolverInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenanceActorResolverInterface;
use Nowo\MaintenanceModeBundle\Security\MaintenancePanelAccessGateInterface;
use ReflectionMethod;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function array_key_exists;
use function is_string;
use function sprintf;

/**
 * Wires the panel to the access gate and the actor resolver the host actually chose (since 1.17, audit MK-S-M1/L4).
 *
 * Each port has two aliases: the deprecated one (`Request`) and the framework-free one. The extension points both to
 * the same service (the configured `security.access_gate` / `security.actor_resolver`, or the bundle default). A host
 * may then alias or decorate only one of them in its own service configuration. After decoration, per port:
 *
 * 1. both aliases resolve to the same service: that service;
 * 2. only the new alias was changed: the new one (also when it is decorated with `#[AsDecorator]`);
 * 3. only the deprecated alias was changed: the deprecated one (as in 1.16, with a runtime deprecation);
 * 4. both were changed, to different services: the container build fails, so a security port is never chosen silently.
 *
 * Runs before the removing passes, while the aliases still exist and after DecoratorServicePass.
 */
final class AccessPortsPass implements CompilerPassInterface
{
    /** Service id the extension chose for the access gate (hidden parameter, removed by this pass). */
    public const GATE_PARAMETER = '.nowo_maintenance_mode.access_gate_id';

    /** Service id the extension chose for the actor resolver (hidden parameter, removed by this pass). */
    public const ACTOR_PARAMETER = '.nowo_maintenance_mode.actor_resolver_id';

    /**
     * @throws LogicException When the deprecated and the new alias of one port point to two different host services
     */
    public function process(ContainerBuilder $container): void
    {
        $gateDefault  = $this->takeParameter($container, self::GATE_PARAMETER);
        $actorDefault = $this->takeParameter($container, self::ACTOR_PARAMETER);

        // The extension removes the controller when web_ui.enabled is false.
        if (!$container->hasDefinition(MaintenancePanelController::class)) {
            return;
        }

        $controller = $container->getDefinition(MaintenancePanelController::class);
        if ($gateDefault !== null) {
            $this->replace($controller, 'accessGate', new Reference($this->choose($container, MaintenanceAccessGateInterface::class, MaintenancePanelAccessGateInterface::class, $gateDefault)));
        }
        if ($actorDefault !== null) {
            $this->replace($controller, 'actorResolver', new Reference($this->choose($container, ActorResolverInterface::class, MaintenanceActorResolverInterface::class, $actorDefault)));
        }
    }

    /**
     * Sets one constructor argument, by name before ResolveNamedArgumentsPass or by position after it.
     */
    private function replace(Definition $controller, string $name, Reference $reference): void
    {
        $arguments = $controller->getArguments();
        if (array_key_exists('$' . $name, $arguments)) {
            $controller->setArgument('$' . $name, $reference);

            return;
        }
        foreach ((new ReflectionMethod(MaintenancePanelController::class, '__construct'))->getParameters() as $parameter) {
            if ($parameter->getName() === $name) {
                $controller->setArgument($parameter->getPosition(), $reference);

                return;
            }
        }
    }

    private function takeParameter(ContainerBuilder $container, string $name): ?string
    {
        if (!$container->hasParameter($name)) {
            return null;
        }
        $value = $container->getParameter($name);
        $container->getParameterBag()->remove($name);

        return is_string($value) ? $value : null;
    }

    /**
     * @return string The service id the panel uses for this port
     */
    private function choose(ContainerBuilder $container, string $legacyId, string $newId, string $defaultId): string
    {
        $default = $this->resolve($container, $defaultId);
        $legacy  = $this->resolve($container, $legacyId);
        $new     = $this->resolve($container, $newId);

        if ($legacy === $new || $legacy === $default) {
            return $new;
        }
        if ($new === $default) {
            return $legacy;
        }

        throw new LogicException(sprintf('The maintenance panel cannot choose between "%s" (aliased to "%s") and "%s" (aliased to "%s"): alias one service to both, or only the new interface.', $legacyId, $legacy, $newId, $new));
    }

    /**
     * Follows aliases to the service id that is defined.
     */
    private function resolve(ContainerBuilder $container, string $id): string
    {
        $seen = [];
        while ($container->hasAlias($id) && !isset($seen[$id])) {
            $seen[$id] = true;
            $id        = (string) $container->getAlias($id);
        }

        return $id;
    }
}
