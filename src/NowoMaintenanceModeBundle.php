<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle;

use Nowo\MaintenanceModeBundle\DependencyInjection\Compiler\TwigPathsPass;
use Nowo\MaintenanceModeBundle\DependencyInjection\MaintenanceModeExtension;
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
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if ($this->extension === null) {
            $this->extension = new MaintenanceModeExtension();
        }

        return $this->extension instanceof ExtensionInterface ? $this->extension : null;
    }
}
