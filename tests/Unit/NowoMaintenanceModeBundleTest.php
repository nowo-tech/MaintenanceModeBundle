<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit;

use Nowo\MaintenanceModeBundle\DependencyInjection\Compiler\CsrfProtectionPass;
use Nowo\MaintenanceModeBundle\DependencyInjection\MaintenanceModeExtension;
use Nowo\MaintenanceModeBundle\NowoMaintenanceModeBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class NowoMaintenanceModeBundleTest extends TestCase
{
    public function testGetContainerExtensionReturnsMaintenanceModeExtension(): void
    {
        $bundle = new NowoMaintenanceModeBundle();

        $extension = $bundle->getContainerExtension();

        self::assertInstanceOf(MaintenanceModeExtension::class, $extension);
    }

    public function testBuildRegistersTwigPathsCompilerPass(): void
    {
        $container = new ContainerBuilder();
        (new NowoMaintenanceModeBundle())->build($container);

        self::assertNotEmpty($container->getCompilerPassConfig()->getPasses());
        self::assertContains(
            CsrfProtectionPass::class,
            array_map(static fn (object $pass): string => $pass::class, $container->getCompilerPassConfig()->getPasses()),
        );
    }
}
