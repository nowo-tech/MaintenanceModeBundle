<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Controller;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

use function str_contains;

use const DATE_ATOM;

/**
 * Dev preview of the configured public maintenance page (like FrameworkBundle /_error/{code}).
 *
 * Enabled by default when kernel.debug is true. Always excluded from the 503 subscriber.
 */
final class MaintenancePreviewController
{
    public function __construct(
        private readonly bool $enabled,
        private readonly MaintenanceManager $manager,
        private readonly Environment $twig,
        private readonly string $template,
        private readonly ?string $defaultMessage,
        private readonly int $statusCode = Response::HTTP_SERVICE_UNAVAILABLE,
        private readonly int $retryAfter = 3600,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->enabled) {
            throw new NotFoundHttpException('Maintenance page preview is disabled. Set nowo_maintenance_mode.preview.enabled: true (default: kernel.debug).');
        }

        $state   = $this->manager->getState();
        $message = $request->query->getString('message');
        if ($message === '') {
            $message = $state->getMessage() ?? $this->defaultMessage;
        }
        // Empty string → null so Twig can fall back to translated maintenance.page.message
        if ($message === '') {
            $message = null;
        }

        $retryAfter = $this->resolveRetryAfter($state->getScheduledDisableAt());
        $wantsJson  = $request->getRequestFormat(null) === 'json'
            || $request->getPreferredFormat('html') === 'json'
            || str_contains((string) $request->headers->get('Accept', ''), 'application/json');

        if ($wantsJson) {
            $response = new JsonResponse([
                'status'               => 'maintenance',
                'preview'              => true,
                'message'              => $message,
                'retry_after'          => $retryAfter,
                'scheduled_disable_at' => $state->getScheduledDisableAt()?->format(DATE_ATOM),
            ], $this->statusCode);
        } else {
            $previewState = $state->withMessage($message);
            $response     = new Response($this->twig->render($this->template, [
                'message' => $message,
                'state'   => $previewState,
            ]), $this->statusCode);
        }

        $response->headers->set('Retry-After', (string) $retryAfter);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->headers->set('X-Maintenance-Preview', '1');

        return $response;
    }

    private function resolveRetryAfter(?DateTimeImmutable $until): int
    {
        if ($until instanceof DateTimeImmutable) {
            $seconds = $until->getTimestamp() - (new DateTimeImmutable())->getTimestamp();
            if ($seconds > 0) {
                return $seconds;
            }
        }

        return $this->retryAfter;
    }
}
