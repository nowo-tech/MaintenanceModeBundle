<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\EventSubscriber;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Attribute\ExcludeFromMaintenance;
use Nowo\MaintenanceModeBundle\Exclusion\MaintenanceExclusionMatcher;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Twig\Environment;

use function explode;
use function hash_equals;
use function htmlspecialchars;
use function is_string;
use function str_contains;
use function str_starts_with;

use const DATE_ATOM;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

/**
 * Intercepts main requests when maintenance is effectively enabled.
 *
 * Registered as kernel.event_listener (not EventSubscriberInterface) so
 * `subscriber_priority` from config is honoured.
 */
final class MaintenanceRequestSubscriber
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
        private readonly ?string $bypassToken = null,
        private readonly string $bypassQueryParameter = 'maintenance_bypass',
        private readonly string $bypassCookieName = 'nowo_maintenance_bypass',
        private readonly bool $bypassSetCookie = true,
    ) {
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

        if ($this->hasValidBypass($request)) {
            if ($this->bypassSetCookie && $this->bypassToken !== null && $this->bypassToken !== '') {
                $event->getRequest()->attributes->set('_nowo_maintenance_bypass_cookie', $this->bypassToken);
            }

            return;
        }

        if ($this->exclusionMatcher->matches($request)) {
            return;
        }

        if ($request->attributes->getBoolean(ExcludeFromMaintenance::ROUTE_DEFAULT)) {
            return;
        }

        if ($this->controllerExcluded($request)) {
            return;
        }

        $state = $this->manager->getState();
        if (!$state->isEffectivelyEnabled()) {
            return;
        }

        $response = $this->createResponse($request, $state);
        $event->setResponse($response);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $event->getRequest()->attributes->get('_nowo_maintenance_bypass_cookie');
        if (!is_string($token) || $token === '') {
            return;
        }

        $event->getResponse()->headers->setCookie(
            Cookie::create($this->bypassCookieName, $token)
                ->withHttpOnly(true)
                ->withSecure($event->getRequest()->isSecure())
                ->withPath('/'),
        );
    }

    private function hasValidBypass(Request $request): bool
    {
        if ($this->bypassToken === null || $this->bypassToken === '') {
            return false;
        }

        $queryToken  = $request->query->getString($this->bypassQueryParameter);
        $cookieToken = (string) $request->cookies->get($this->bypassCookieName, '');

        return ($queryToken !== '' && hash_equals($this->bypassToken, $queryToken))
            || ($cookieToken !== '' && hash_equals($this->bypassToken, $cookieToken));
    }

    private function controllerExcluded(Request $request): bool
    {
        $controller = $request->attributes->get('_controller');
        if (!is_string($controller) || $controller === '') {
            return false;
        }

        try {
            if (str_contains($controller, '::')) {
                [$class, $method] = explode('::', $controller, 2);
                $refMethod        = new ReflectionMethod($class, $method);
                if ($refMethod->getAttributes(ExcludeFromMaintenance::class) !== []) {
                    return true;
                }

                return $refMethod->getDeclaringClass()->getAttributes(ExcludeFromMaintenance::class) !== []

                ;
            }

            if (class_exists($controller)) {
                $refClass = new ReflectionClass($controller);
                if ($refClass->getAttributes(ExcludeFromMaintenance::class) !== []) {
                    return true;
                }
                if ($refClass->hasMethod('__invoke')) {
                    return $refClass->getMethod('__invoke')->getAttributes(ExcludeFromMaintenance::class) !== [];
                }
            }
        } catch (ReflectionException) {
            return false;
        }

        return false;
    }

    private function createResponse(Request $request, MaintenanceState $state): Response
    {
        $retryAfter = $this->resolveRetryAfter($state);
        $wantsJson  = $this->wantsJson($request);

        if ($wantsJson) {
            $response = new JsonResponse([
                'status'               => 'maintenance',
                'message'              => $state->getMessage(),
                'retry_after'          => $retryAfter,
                'scheduled_disable_at' => $state->getScheduledDisableAt()?->format(DATE_ATOM),
            ], $this->statusCode);
        } else {
            $response = new Response($this->renderHtml($state), $this->statusCode);
        }

        $response->headers->set('Retry-After', (string) $retryAfter);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    private function wantsJson(Request $request): bool
    {
        $format = $request->getRequestFormat(null);
        if ($format === 'json') {
            return true;
        }

        return $request->getPreferredFormat('html') === 'json'
            || str_contains((string) $request->headers->get('Accept', ''), 'application/json');
    }

    private function resolveRetryAfter(MaintenanceState $state): int
    {
        $until = $state->getScheduledDisableAt();
        if ($until instanceof DateTimeImmutable) {
            $seconds = $until->getTimestamp() - (new DateTimeImmutable())->getTimestamp();
            if ($seconds > 0) {
                return $seconds;
            }
        }

        return $this->retryAfter;
    }

    private function renderHtml(MaintenanceState $state): string
    {
        $message = $state->getMessage();

        if ($this->twig instanceof Environment) {
            return $this->twig->render($this->template, [
                'message' => $message,
                'state'   => $state,
            ]);
        }

        $safe = htmlspecialchars($message ?? "We're making a few gentle improvements. Everything you care about is safe.", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Maintenance</title></head><body><h1>Maintenance</h1><p>' . $safe . '</p></body></html>';
    }
}
