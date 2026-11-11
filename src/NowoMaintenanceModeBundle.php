<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle;

use Nowo\MaintenanceModeBundle\DependencyInjection\Compiler\AccessPortsPass;
use Nowo\MaintenanceModeBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\MaintenanceModeBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\MaintenanceModeBundle\DependencyInjection\MaintenanceModeExtension;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class NowoMaintenanceModeBundle extends Bundle
{
    public const TRANSLATION_DOMAIN = 'NowoMaintenanceModeBundle';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new TwigPathsPass());
        $container->addCompilerPass(new CsrfProtectionPass());
        // After decoration (optimization passes), before the private aliases are removed.
        $container->addCompilerPass(new AccessPortsPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            // @igor-ignore - Symfony bundle extension lazy-init at boot; not request state
            $this->extension = new MaintenanceModeExtension();
        }

        return $this->extension instanceof ExtensionInterface ? $this->extension : null;
    }
}
