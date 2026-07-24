<?php

declare(strict_types=1);

namespace App\Controller;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Preview calm maintenance page examples (excluded from 503 in demo config).
 */
final class MaintenanceExamplesController extends AbstractController
{
    /** @var array<string, array{template: string, framework: string, style: string}> */
    private const EXAMPLES = [
        'bootstrap-calm' => [
            'template'  => '@NowoMaintenanceModeBundle/maintenance/examples/bootstrap_calm.html.twig',
            'framework' => 'Bootstrap 5.3',
            'style'     => 'Soft teal calm card',
        ],
        'bootstrap-sunset' => [
            'template'  => '@NowoMaintenanceModeBundle/maintenance/examples/bootstrap_sunset.html.twig',
            'framework' => 'Bootstrap 5.3',
            'style'     => 'Warm sunset / peach',
        ],
        'foundation-garden' => [
            'template'  => '@NowoMaintenanceModeBundle/maintenance/examples/foundation_garden.html.twig',
            'framework' => 'Foundation 6.9',
            'style'     => 'Sage garden',
        ],
        'foundation-night' => [
            'template'  => '@NowoMaintenanceModeBundle/maintenance/examples/foundation_night.html.twig',
            'framework' => 'Foundation 6.9',
            'style'     => 'Calm night / slate',
        ],
        'tailwind-breeze' => [
            'template'  => '@NowoMaintenanceModeBundle/maintenance/examples/tailwind_breeze.html.twig',
            'framework' => 'Tailwind CSS 4',
            'style'     => 'Mint breeze',
        ],
        'tailwind-aurora' => [
            'template'  => '@NowoMaintenanceModeBundle/maintenance/examples/tailwind_aurora.html.twig',
            'framework' => 'Tailwind CSS 4',
            'style'     => 'Aurora dusk',
        ],
        'idea-short' => [
            'template'  => '@NowoMaintenanceModeBundle/maintenance/examples/idea_short.html.twig',
            'framework' => 'Bootstrap 5.3',
            'style'     => 'Short & sweet',
        ],
        'idea-compassion' => [
            'template'  => '@NowoMaintenanceModeBundle/maintenance/examples/idea_compassion.html.twig',
            'framework' => 'Bootstrap 5.3',
            'style'     => 'Compassionate apology',
        ],
        'idea-playful' => [
            'template'  => '@NowoMaintenanceModeBundle/maintenance/examples/idea_playful.html.twig',
            'framework' => 'Tailwind CSS 4',
            'style'     => 'Playful light humor',
        ],
        'idea-brand' => [
            'template'  => '@NowoMaintenanceModeBundle/maintenance/examples/idea_brand.html.twig',
            'framework' => 'Vanilla CSS',
            'style'     => 'Familiar brand look',
        ],
        'idea-countdown' => [
            'template'  => '@NowoMaintenanceModeBundle/maintenance/examples/idea_countdown.html.twig',
            'framework' => 'Tailwind CSS 4',
            'style'     => 'Countdown to return',
        ],
        'idea-updates' => [
            'template'  => '@NowoMaintenanceModeBundle/maintenance/examples/idea_updates.html.twig',
            'framework' => 'Foundation 6.9',
            'style'     => 'Progress & status log',
        ],
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(path: '/examples', name: 'maintenance_examples', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('examples/index.html.twig', [
            'examples' => self::EXAMPLES,
            'locales'  => ['en', 'es', 'it', 'fr', 'pt', 'de', 'nl'],
        ]);
    }

    #[Route(path: '/examples/{slug}', name: 'maintenance_example_show', methods: ['GET'])]
    public function show(string $slug, Request $request): Response
    {
        if (!isset(self::EXAMPLES[$slug])) {
            throw $this->createNotFoundException(sprintf('Unknown example "%s".', $slug));
        }

        $locale = $request->query->getString('_locale');
        if ($locale !== '') {
            $request->setLocale($locale);
            if ($this->translator instanceof LocaleAwareInterface) {
                $this->translator->setLocale($locale);
            }
        }

        $message = $request->query->get('message');
        $state   = (new MaintenanceState())
            ->withEnabled(true)
            ->withScheduledDisableAt(new DateTimeImmutable('+45 minutes'));

        return $this->render(self::EXAMPLES[$slug]['template'], [
            'message' => is_string($message) && $message !== '' ? $message : null,
            'state'   => $state,
        ]);
    }
}
