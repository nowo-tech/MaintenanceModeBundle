<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\EventSubscriber;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Attribute\ExcludeFromMaintenance;
use Nowo\MaintenanceModeBundle\EventSubscriber\MaintenanceRequestSubscriber;
use Nowo\MaintenanceModeBundle\Exclusion\MaintenanceExclusionMatcher;
use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Twig\Environment;

final class MaintenanceRequestSubscriberTest extends TestCase
{
    public function testReturns503WhenEnabled(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true)->withMessage('Down'),
            twig: null,
        );

        $event = $this->createMainRequestEvent('/page');
        $subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(Response::class, $response);
        self::assertSame(503, $response->getStatusCode());
        self::assertStringContainsString('Down', (string) $response->getContent());
        self::assertSame('3600', $response->headers->get('Retry-After'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testReturnsJsonWhenAcceptJson(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true)->withMessage('API down'),
            twig: null,
        );

        $request = Request::create('/api/items', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $event   = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertStringContainsString('API down', (string) $response->getContent());
        self::assertStringContainsString('"status":"maintenance"', (string) $response->getContent());
    }

    public function testDynamicRetryAfterFromSchedule(): void
    {
        $until      = (new DateTimeImmutable('+2 hours'));
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true)->withScheduledDisableAt($until),
            twig: null,
        );

        $event = $this->createMainRequestEvent('/page');
        $subscriber->onKernelRequest($event);

        $retry = (int) $event->getResponse()?->headers->get('Retry-After');
        self::assertGreaterThan(7000, $retry);
        self::assertLessThanOrEqual(7200, $retry);
    }

    public function testRendersViaTwigWhenAvailable(): void
    {
        $state = (new MaintenanceState())->withEnabled(true)->withMessage('Twig message');
        $twig  = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with(
                '@NowoMaintenanceModeBundle/maintenance/page.html.twig',
                self::callback(static function (array $ctx) use ($state): bool {
                    return ($ctx['message'] ?? null) === 'Twig message'
                        && ($ctx['state'] ?? null) instanceof MaintenanceState
                        && $ctx['state']->getMessage() === $state->getMessage();
                }),
            )
            ->willReturn('<html>maintenance</html>');

        $subscriber = $this->createSubscriber(state: $state, twig: $twig);

        $event = $this->createMainRequestEvent('/page');
        $subscriber->onKernelRequest($event);

        self::assertSame('<html>maintenance</html>', $event->getResponse()?->getContent());
    }

    public function testFallbackHtmlUsesDefaultMessageWhenNull(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true),
            twig: null,
        );

        $event = $this->createMainRequestEvent('/page');
        $subscriber->onKernelRequest($event);

        self::assertStringContainsString(
            'gentle improvements',
            (string) $event->getResponse()?->getContent(),
        );
    }

    public function testSkipsExcludedPanelPrefix(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true),
            twig: null,
            panelPathPrefix: '/_maintenance',
        );

        $event = $this->createMainRequestEvent('/_maintenance');
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSkipsWhenMasterSwitchDisabled(): void
    {
        $subscriber = new MaintenanceRequestSubscriber(
            enabled: false,
            manager: $this->createManager((new MaintenanceState())->withEnabled(true)),
            exclusionMatcher: new MaintenanceExclusionMatcher(),
            twig: null,
            template: 'x',
        );

        $event = $this->createMainRequestEvent('/page');
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSkipsSubRequests(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true),
            twig: null,
        );

        $kernel = $this->createMock(HttpKernelInterface::class);
        $event  = new RequestEvent($kernel, Request::create('/page'), HttpKernelInterface::SUB_REQUEST);
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSkipsWhenExclusionMatcherMatches(): void
    {
        $matcher    = new MaintenanceExclusionMatcher(paths: ['/health']);
        $subscriber = new MaintenanceRequestSubscriber(
            enabled: true,
            manager: $this->createManager((new MaintenanceState())->withEnabled(true)),
            exclusionMatcher: $matcher,
            twig: null,
            template: 'x',
        );

        $event = $this->createMainRequestEvent('/health');
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSkipsWhenStateNotEffectivelyEnabled(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(false),
            twig: null,
        );

        $event = $this->createMainRequestEvent('/page');
        $subscriber->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testCustomStatusCodeAndRetryAfter(): void
    {
        $subscriber = new MaintenanceRequestSubscriber(
            enabled: true,
            manager: $this->createManager((new MaintenanceState())->withEnabled(true)->withMessage('x')),
            exclusionMatcher: new MaintenanceExclusionMatcher(),
            twig: null,
            template: 'x',
            statusCode: 503,
            retryAfter: 120,
        );

        $event = $this->createMainRequestEvent('/page');
        $subscriber->onKernelRequest($event);

        self::assertSame(503, $event->getResponse()?->getStatusCode());
        self::assertSame('120', $event->getResponse()->headers->get('Retry-After'));
    }

    public function testBypassTokenViaQuerySetsCookieOnResponse(): void
    {
        $subscriber = new MaintenanceRequestSubscriber(
            enabled: true,
            manager: $this->createManager((new MaintenanceState())->withEnabled(true)),
            exclusionMatcher: new MaintenanceExclusionMatcher(),
            twig: null,
            template: 'x',
            bypassToken: 'secret-token',
        );

        $request = Request::create('/page', 'GET', ['maintenance_bypass' => 'secret-token']);
        $event   = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());

        $response      = new Response('ok');
        $responseEvent = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );
        $subscriber->onKernelResponse($responseEvent);
        self::assertCount(1, $response->headers->getCookies());
        self::assertSame('secret-token', $response->headers->getCookies()[0]->getValue());
    }

    public function testRouteDefaultExclude(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true),
            twig: null,
        );
        $request = Request::create('/excluded-action');
        $request->attributes->set(ExcludeFromMaintenance::ROUTE_DEFAULT, true);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testControllerAttributeExclude(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true),
            twig: null,
        );
        $request = Request::create('/attr');
        $request->attributes->set('_controller', ExcludedDemoController::class . '::ok');
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testMethodAttributeExclude(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true),
            twig: null,
        );
        $request = Request::create('/method-attr');
        $request->attributes->set('_controller', MethodExcludedDemoController::class . '::ok');
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testInvokableControllerAttributeExclude(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true),
            twig: null,
        );
        $request = Request::create('/invoke');
        $request->attributes->set('_controller', InvokableExcludedController::class);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testInvokableMethodAttributeExclude(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true),
            twig: null,
        );
        $request = Request::create('/invoke-method');
        $request->attributes->set('_controller', InvokableMethodExcludedController::class);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testInvalidControllerIsIgnored(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true)->withMessage('x'),
            twig: null,
        );
        $request = Request::create('/bad');
        $request->attributes->set('_controller', 'App\\DoesNotExist::action');
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);
        self::assertSame(503, $event->getResponse()?->getStatusCode());
    }

    public function testControllerWithoutAttributeIsNotExcluded(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true)->withMessage('x'),
            twig: null,
        );
        $request = Request::create('/plain');
        $request->attributes->set('_controller', PlainDemoController::class . '::ok');
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);
        self::assertSame(503, $event->getResponse()?->getStatusCode());
    }

    public function testNonInvokableClassControllerIsNotExcluded(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true)->withMessage('x'),
            twig: null,
        );
        $request = Request::create('/plain-class');
        $request->attributes->set('_controller', PlainDemoController::class);
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);
        self::assertSame(503, $event->getResponse()?->getStatusCode());
    }

    public function testBypassViaCookie(): void
    {
        $subscriber = new MaintenanceRequestSubscriber(
            enabled: true,
            manager: $this->createManager((new MaintenanceState())->withEnabled(true)),
            exclusionMatcher: new MaintenanceExclusionMatcher(),
            twig: null,
            template: 'x',
            bypassToken: 'secret-token',
        );

        $request = Request::create('/page');
        $request->cookies->set('nowo_maintenance_bypass', 'secret-token');
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
    }

    public function testBypassDoesNotSetCookieWhenDisabled(): void
    {
        $subscriber = new MaintenanceRequestSubscriber(
            enabled: true,
            manager: $this->createManager((new MaintenanceState())->withEnabled(true)),
            exclusionMatcher: new MaintenanceExclusionMatcher(),
            twig: null,
            template: 'x',
            bypassToken: 'secret-token',
            bypassSetCookie: false,
        );

        $request = Request::create('/page', 'GET', ['maintenance_bypass' => 'secret-token']);
        $event   = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);
        self::assertNull($event->getResponse());
        self::assertFalse($request->attributes->has('_nowo_maintenance_bypass_cookie'));
    }

    public function testOnKernelResponseIgnoresSubRequestsAndMissingToken(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true),
            twig: null,
        );
        $kernel   = $this->createMock(HttpKernelInterface::class);
        $request  = Request::create('/page');
        $response = new Response('ok');

        $subscriber->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response));
        self::assertSame([], $response->headers->getCookies());

        $subscriber->onKernelResponse(new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response));
        self::assertSame([], $response->headers->getCookies());
    }

    public function testPastScheduledDisableUsesConfiguredRetryAfter(): void
    {
        $subscriber = new MaintenanceRequestSubscriber(
            enabled: true,
            manager: $this->createManager((new MaintenanceState())->withEnabled(true)),
            exclusionMatcher: new MaintenanceExclusionMatcher(),
            twig: null,
            template: 'x',
            retryAfter: 99,
        );

        $method  = new ReflectionMethod(MaintenanceRequestSubscriber::class, 'resolveRetryAfter');
        $seconds = $method->invoke(
            $subscriber,
            (new MaintenanceState())->withScheduledDisableAt(new DateTimeImmutable('-1 hour')),
        );

        self::assertSame(99, $seconds);
    }

    public function testJsonWhenRequestFormatIsJson(): void
    {
        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true)->withMessage('JSON'),
            twig: null,
        );
        $request = Request::create('/api.json');
        $request->setRequestFormat('json');
        $event = new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event);
        self::assertInstanceOf(JsonResponse::class, $event->getResponse());
    }

    private function createSubscriber(
        MaintenanceState $state,
        ?Environment $twig,
        string $panelPathPrefix = '',
    ): MaintenanceRequestSubscriber {
        return new MaintenanceRequestSubscriber(
            enabled: true,
            manager: $this->createManager($state),
            exclusionMatcher: new MaintenanceExclusionMatcher(),
            twig: $twig,
            template: '@NowoMaintenanceModeBundle/maintenance/page.html.twig',
            panelPathPrefix: $panelPathPrefix,
        );
    }

    private function createManager(MaintenanceState $state): MaintenanceManager
    {
        $stateStorage = new class($state) implements MaintenanceStateStorageInterface {
            public function __construct(private MaintenanceState $state)
            {
            }

            public function load(): MaintenanceState
            {
                return $this->state;
            }

            public function save(MaintenanceState $state): void
            {
                $this->state = $state;
            }
        };
        $historyStorage = new class implements MaintenanceHistoryStorageInterface {
            public function append(MaintenanceHistoryEntry $entry): void
            {
            }

            public function list(int $limit = 50): array
            {
                return [];
            }
        };

        return new MaintenanceManager($stateStorage, $historyStorage);
    }

    private function createMainRequestEvent(string $path): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, Request::create($path), HttpKernelInterface::MAIN_REQUEST);
    }
}

#[ExcludeFromMaintenance]
final class ExcludedDemoController
{
    public function ok(): Response
    {
        return new Response('ok');
    }
}

final class MethodExcludedDemoController
{
    #[ExcludeFromMaintenance]
    public function ok(): Response
    {
        return new Response('ok');
    }
}

#[ExcludeFromMaintenance]
final class InvokableExcludedController
{
    public function __invoke(): Response
    {
        return new Response('ok');
    }
}

final class InvokableMethodExcludedController
{
    #[ExcludeFromMaintenance]
    public function __invoke(): Response
    {
        return new Response('ok');
    }
}

final class PlainDemoController
{
    public function ok(): Response
    {
        return new Response('ok');
    }
}
