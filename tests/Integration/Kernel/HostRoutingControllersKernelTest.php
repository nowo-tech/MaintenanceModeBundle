<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Integration\Kernel;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

use function is_string;
use function str_starts_with;
use function sys_get_temp_dir;
use function uniqid;

/**
 * REQ-SF-004 rule 7: the bundle routes are opt-in. A host whose routing only holds the Symfony default
 * `routing.controllers` resource must not get the panel or preview routes (on Symfony >= 7.3 an autoconfigured
 * #[Route] controller is tagged `routing.controller` and that resource would load it, the panel at `/`).
 */
final class HostRoutingControllersKernelTest extends TestCase
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

    public function testRoutingControllersResourceDoesNotLoadTheBundleRoutes(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('The routing.controllers resource needs symfony/routing >= 7.3.');
        }

        $bundleRoutes = [];
        foreach ($this->routes(false)->all() as $name => $route) {
            $controller = $route->getDefault('_controller');
            if (str_starts_with($name, 'nowo_maintenance_mode') || (is_string($controller) && str_starts_with($controller, 'Nowo\\MaintenanceModeBundle\\'))) {
                $bundleRoutes[] = $name;
            }
        }

        self::assertSame([], $bundleRoutes, 'The bundle routes must only exist when the host imports routes.yaml.');
    }

    public function testImportedRoutesKeepThePanelPrefix(): void
    {
        $routes = $this->routes(true);

        self::assertSame('/_maintenance/', $routes->get('nowo_maintenance_mode_panel_index')?->getPath());
        self::assertNotNull($routes->get('nowo_maintenance_mode_preview'));
    }

    private function routes(bool $importBundleRoutes): RouteCollection
    {
        $this->kernel = new MaintenanceTestKernel(sys_get_temp_dir() . '/nowo-mm-routing-' . uniqid('', true), [], $importBundleRoutes);
        $this->kernel->boot();

        $router = $this->kernel->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return $router->getRouteCollection();
    }
}
