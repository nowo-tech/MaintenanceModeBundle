<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\EventSubscriber;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Attribute\ExcludeFromMaintenance;
use Nowo\MaintenanceModeBundle\Exclusion\MaintenanceExclusionMatcher;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManagerInterface;
use Nowo\MaintenanceModeBundle\View\MaintenancePages;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Throwable;
use Twig\Environment;

use function explode;
use function hash_equals;
use function hash_hmac;
use function htmlspecialchars;
use function is_string;
use function rtrim;
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
    /**
     * HMAC label used to derive the bypass cookie value from the bypass token
     * (the cookie never carries the raw shared secret).
     */
    private const BYPASS_COOKIE_CONTEXT = 'nowo_maintenance_mode.bypass_cookie.v1';

    /**
     * `storage.on_error`: serve the request as if maintenance were off. Opt-in, only for a state storage that is
     * independent of the application's database: it serves the live site while maintenance may be on.
     */
    public const ON_ERROR_FAIL_OPEN = 'fail_open';

    /** `storage.on_error` (default): serve the maintenance page (503 + Retry-After), no application code runs. */
    public const ON_ERROR_FAIL_CLOSED = 'fail_closed';

    /** `storage.on_error`: let the storage exception through (1.x behaviour). */
    public const ON_ERROR_RETHROW = 'rethrow';

    /** A storage failure is logged at most once per this many seconds per process (the rest are dropped). */
    public const ERROR_LOG_INTERVAL = 60;

    /** Unix time of the last logged storage failure (rate limit of the error log; not request data). */
    private int $lastErrorLoggedAt = 0;

    /**
     * @param string $onStorageError What to do when the state storage throws (see the ON_ERROR_* constants)
     * @param LoggerInterface|null $logger Receives the storage failure (error level, at most once per ERROR_LOG_INTERVAL)
     * @param string|null $defaultMessage Message of the fail_closed page (`default_message`)
     */
    public function __construct(
        private readonly bool $enabled,
        private readonly MaintenanceManagerInterface $manager,
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
        private readonly ?ClockInterface $clock = null,
        private readonly string $onStorageError = self::ON_ERROR_FAIL_CLOSED,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?string $defaultMessage = null,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$this->enabled || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path    = $request->getPathInfo();

        if ($this->isPanelPath($path)) {
            return;
        }

        if ($this->hasValidBypass($request)) {
            if ($this->bypassSetCookie) {
                $event->getRequest()->attributes->set('_nowo_maintenance_bypass_cookie', $this->bypassCookieValue());
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

        $state = $this->loadState();
        if (!$state instanceof MaintenanceState || !$state->isEffectivelyEnabled($this->now())) {
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

    /**
     * Reads the state; a throwing storage (database down, often the reason for maintenance) must not turn every
     * request into a 500. fail_closed (default) returns an enabled state with `default_message` (maintenance page),
     * fail_open returns null (request served), rethrow lets the exception through.
     */
    private function loadState(): ?MaintenanceState
    {
        try {
            return $this->manager->getState();
        } catch (Throwable $e) {
            if ($this->onStorageError === self::ON_ERROR_RETHROW) {
                throw $e;
            }

            $this->logStorageError($e);

            return $this->onStorageError === self::ON_ERROR_FAIL_OPEN ? null : new MaintenanceState(enabled: true, message: $this->defaultMessage);
        }
    }

    private function logStorageError(Throwable $e): void
    {
        $now = $this->now()->getTimestamp();
        if ($this->lastErrorLoggedAt !== 0 && $now - $this->lastErrorLoggedAt < self::ERROR_LOG_INTERVAL) {
            return;
        }
        // @igor-ignore - Log rate limit (a timestamp); never request or user data, safe to share between requests.
        $this->lastErrorLoggedAt = $now;

        $this->logger?->error('Maintenance mode state could not be loaded; applying storage.on_error.', [
            'bundle'    => 'maintenance_mode',
            'on_error'  => $this->onStorageError,
            'exception' => $e,
        ]);
    }

    /**
     * Segment-aware prefix match: "/_maintenance" matches "/_maintenance" and "/_maintenance/…",
     * but not "/_maintenance-public".
     */
    private function isPanelPath(string $path): bool
    {
        $prefix = rtrim($this->panelPathPrefix, '/');
        if ($prefix === '') {
            return $this->panelPathPrefix === '/';
        }

        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    private function hasValidBypass(Request $request): bool
    {
        if ($this->bypassToken === null || $this->bypassToken === '') {
            return false;
        }

        $queryToken  = $request->query->getString($this->bypassQueryParameter);
        $cookieToken = (string) $request->cookies->get($this->bypassCookieName, '');

        return ($queryToken !== '' && hash_equals($this->bypassToken, $queryToken))
            || ($cookieToken !== '' && hash_equals($this->bypassCookieValue(), $cookieToken));
    }

    /**
     * Cookie value derived from the bypass token; rotating the token invalidates existing cookies.
     */
    private function bypassCookieValue(): string
    {
        return hash_hmac('sha256', self::BYPASS_COOKIE_CONTEXT, (string) $this->bypassToken);
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

    /**
     * Current time from the injected clock (REQ-DI-001), system time when none is wired.
     */
    private function now(): DateTimeImmutable
    {
        return $this->clock?->now() ?? new DateTimeImmutable();
    }

    private function resolveRetryAfter(MaintenanceState $state): int
    {
        $until = $state->getScheduledDisableAt();
        if ($until instanceof DateTimeImmutable) {
            $seconds = $until->getTimestamp() - $this->now()->getTimestamp();
            if ($seconds > 0) {
                return $seconds;
            }
        }

        return $this->retryAfter;
    }

    /**
     * Renders the public page (templates.page: UiKit guest layout + theme by default). A failing template (host
     * layout, appearance provider or override touching a database that is down) must never turn the 503 into a 500:
     * the minimal built-in page is served instead (check templates with the preview route, which does not fall back).
     */
    private function renderHtml(MaintenanceState $state): string
    {
        $message = $state->getMessage();

        if ($this->twig instanceof Environment) {
            try {
                return $this->twig->render($this->template, [
                    'message' => $message,
                    'state'   => $state,
                    'page'    => MaintenancePages::maintenance($message),
                ]);
            } catch (Throwable) {
                // Fall through to the built-in page.
            }
        }

        $safe = htmlspecialchars($message ?? "We're making a few gentle improvements. Everything you care about is safe.", ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>Maintenance</title></head><body><h1>Maintenance</h1><p>' . $safe . '</p></body></html>';
    }
}
