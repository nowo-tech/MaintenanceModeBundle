<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Integration\Kernel;

use Nowo\MaintenanceModeBundle\NowoMaintenanceModeBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal host app (FrameworkBundle + TwigBundle + UiKit + this bundle, no SecurityBundle, no database) for the
 * functional tests of the 503 and the panel pages. Each instance owns its cache / log / state directory.
 */
final class MaintenanceTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $bundleConfig extra nowo_maintenance_mode config
     * @param bool $importBundleRoutes whether the host imports the bundle routes.yaml (the Symfony default
     *                                 `routing.controllers` resource is always imported)
     */
    public function __construct(
        private readonly string $dir,
        private readonly array $bundleConfig = [],
        private readonly bool $importBundleRoutes = true,
    ) {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new NowoUiKitBundle();
        yield new NowoMaintenanceModeBundle();
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function getCacheDir(): string
    {
        return $this->dir . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->dir . '/log';
    }

    public function home(): Response
    {
        return new Response('<p>home</p>');
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'test',
            'test'                 => true,
            'http_method_override' => false,
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'csrf_protection'      => true,
            'form'                 => true,
            'translator'           => ['default_path' => '%kernel.project_dir%/translations', 'fallbacks' => ['en']],
            'router'               => ['utf8' => true],
            'assets'               => true,
        ]);
        $container->extension('twig', ['strict_variables' => true]);
        $container->extension('nowo_maintenance_mode', $this->bundleConfig + [
            'security' => ['allow_unauthenticated' => true, 'password_protection' => false],
            'storage'  => [
                'state_file'   => $this->dir . '/state.json',
                'history_file' => $this->dir . '/history.jsonl',
            ],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // The Symfony default `controllers: { resource: routing.controllers }` of a Flex host.
        $routes->import('routing.controllers');
        if ($this->importBundleRoutes) {
            $routes->import('@NowoMaintenanceModeBundle/Resources/config/routes.yaml');
        }
        $routes->add('app_home', '/')->controller([$this, 'home']);
    }
}
