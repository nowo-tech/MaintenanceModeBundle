<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Twig;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Form\ClearScheduleType;
use Nowo\MaintenanceModeBundle\Form\DisableMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\EnableMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\LoginMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\LogoutMaintenanceType;
use Nowo\MaintenanceModeBundle\Form\ScheduleMaintenanceType;
use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryHistoryStorage;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryStateStorage;
use Nowo\MaintenanceModeBundle\Twig\MaintenanceExtension;
use Symfony\Bridge\Twig\Extension\FormExtension;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Bridge\Twig\Form\TwigRendererEngine;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormRenderer;
use Symfony\Component\Form\Forms;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFunction;

use function basename;
use function dirname;
use function explode;
use function glob;

/**
 * Real Twig environment for bundle template render tests (bundle views + UiKit macros + form/trans extensions).
 */
final class TemplateRenderHarness
{
    public const FIXTURE_DIR = __DIR__ . '/../../Fixtures/render';

    /**
     * @param array<string, mixed> $chrome Twig extension `$chrome` argument (null = extension default)
     * @param array<string, string> $extraTemplates Host templates (name => source), resolved before bundle views
     */
    public static function environment(?array $chrome = null, array $extraTemplates = [], string $cssFramework = 'custom'): Environment
    {
        $root = dirname(__DIR__, 3);
        $fs   = new FilesystemLoader([], $root);
        $fs->addPath($root . '/src/Resources/views', 'NowoMaintenanceModeBundle');
        $fs->addPath($root . '/vendor/nowo-tech/ui-kit-bundle/src/Resources/views', 'NowoUiKitBundle');
        $fs->addPath($root . '/vendor/symfony/twig-bridge/Resources/views/Form');

        $twig = new Environment(new ChainLoader([new ArrayLoader($extraTemplates), $fs]), [
            'strict_variables' => true,
            'cache'            => false,
        ]);

        $translator = new Translator('en');
        $translator->addLoader('yaml', new YamlFileLoader());
        foreach (glob($root . '/src/Resources/translations/*.yaml') ?: [] as $file) {
            $parts = explode('.', basename($file));
            $translator->addResource('yaml', $file, $parts[1], $parts[0]);
        }

        $twig->addExtension(new TranslationExtension($translator));
        $twig->addExtension(new FormExtension());
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([
            FormRenderer::class => static fn (): FormRenderer => new FormRenderer(new TwigRendererEngine(['form_div_layout.html.twig'], $twig)),
        ]));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path, ?string $package = null): string => '/bundles/' . ($package ?? 'app') . '/' . $path));

        $manager   = new MaintenanceManager(new InMemoryStateStorage(), new InMemoryHistoryStorage());
        $extension = $chrome === null
            ? new MaintenanceExtension($manager, '@NowoMaintenanceModeBundle/panel/layout.html.twig', $cssFramework)
            : new MaintenanceExtension($manager, '@NowoMaintenanceModeBundle/panel/layout.html.twig', $cssFramework, 'none', $chrome);
        $twig->addExtension($extension);

        return $twig;
    }

    public static function state(): MaintenanceState
    {
        return (new MaintenanceState())
            ->withEnabled(true)
            ->withMessage('Back soon')
            ->withActivatedAt(new DateTimeImmutable('2026-01-02 03:04:05'))
            ->withUpdatedBy('ops');
    }

    /**
     * @return array<string, mixed>
     */
    public static function indexContext(bool $passwordRequired = true): array
    {
        $factory = self::formFactory();
        $state   = self::state();
        $opts    = static fn (string $action): array => ['csrf_protection' => false, 'action' => '/_maintenance/' . $action];

        return [
            'state'               => $state,
            'path_prefix'         => '/_maintenance',
            'flashes'             => ['success' => ['panel.flash.enabled']],
            'password_required'   => $passwordRequired,
            'enable_form'         => $factory->createNamed('', EnableMaintenanceType::class, ['message' => $state->getMessage()], $opts('enable'))->createView(),
            'disable_form'        => $factory->createNamed('', DisableMaintenanceType::class, null, $opts('disable'))->createView(),
            'schedule_form'       => $factory->createNamed('', ScheduleMaintenanceType::class, null, $opts('schedule'))->createView(),
            'clear_schedule_form' => $factory->createNamed('', ClearScheduleType::class, null, $opts('clear-schedule'))->createView(),
            'logout_form'         => $factory->createNamed('', LogoutMaintenanceType::class, null, $opts('logout'))->createView(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function historyContext(bool $withEntries = true): array
    {
        return [
            'path_prefix' => '/_maintenance',
            'entries'     => $withEntries ? [
                new MaintenanceHistoryEntry('enable', new DateTimeImmutable('2026-01-02 03:04:05'), 'ops', 'Deploy'),
                new MaintenanceHistoryEntry('disable', new DateTimeImmutable('2026-01-02 04:00:00')),
            ] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function loginContext(?string $error = 'Invalid password.'): array
    {
        return [
            'path_prefix' => '/_maintenance',
            'error'       => $error,
            'login_form'  => self::formFactory()->createNamed('', LoginMaintenanceType::class, null, ['csrf_protection' => false, 'action' => '/_maintenance/login'])->createView(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function pageContext(): array
    {
        return ['message' => 'Back soon', 'state' => self::state()];
    }

    private static function formFactory(): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()->getFormFactory();
    }
}
