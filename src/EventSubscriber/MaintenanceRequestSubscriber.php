<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\EventSubscriber;

use Nowo\MaintenanceModeBundle\Exclusion\MaintenanceExclusionMatcher;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Environment;

use function htmlspecialchars;
use function str_starts_with;

use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Intercepts main requests when maintenance is effectively enabled.
 */
final class MaintenanceRequestSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly bool $enabled,
        private readonly MaintenanceManager $manager,
        private readonly MaintenanceExclusionMatcher $exclusionMatcher,
        private readonly ?Environment $twig,
        private readonly string $template,
        private readonly int $statusCode = Response::HTTP_SERVICE_UNAVAILABLE,
        private readonly int $retryAfter = 3600,
        private readonly string $panelPathPrefix = '/_maintenance',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 32],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path    = $request->getPathInfo();

        if ($this->panelPathPrefix !== '' && str_starts_with($path, $this->panelPathPrefix)) {
            return;
        }

        if ($this->exclusionMatcher->matches($request)) {
            return;
        }

        $state = $this->manager->getState();
        if (!$state->isEffectivelyEnabled()) {
            return;
        }

        $content  = $this->render($state->getMessage());
        $response = new Response($content, $this->statusCode);
        $response->headers->set('Retry-After', (string) $this->retryAfter);

        $event->setResponse($response);
    }

    private function render(?string $message): string
    {
        if ($this->twig instanceof Environment) {
            return $this->twig->render($this->template, [
                'message' => $message,
            ]);
        }

        $safe = htmlspecialchars($message ?? 'The site is temporarily unavailable for maintenance.', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Maintenance</title></head><body><h1>Maintenance</h1><p>' . $safe . '</p></body></html>';
    }
}
