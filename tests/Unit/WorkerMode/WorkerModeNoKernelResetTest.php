<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\WorkerMode;

use Nowo\MaintenanceModeBundle\EventSubscriber\MaintenanceRequestSubscriber;
use Nowo\MaintenanceModeBundle\Exclusion\MaintenanceExclusionMatcher;
use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Security\PasswordMaintenanceAccessGate;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use const PASSWORD_BCRYPT;

/**
 * Simulates FrankenPHP worker scenario B: the same service instances handle consecutive
 * HTTP requests without Kernel::reboot() / services_resetter (reset_kernel false).
 */
final class WorkerModeNoKernelResetTest extends TestCase
{
    public function testSharedSubscriberSeesDiskStateChangesAcrossRequestsWithoutReset(): void
    {
        $stateStorage = new class implements MaintenanceStateStorageInterface {
            public MaintenanceState $state;

            public function __construct()
            {
                $this->state = new MaintenanceState();
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

        $manager    = new MaintenanceManager($stateStorage, $historyStorage);
        $subscriber = new MaintenanceRequestSubscriber(
            enabled: true,
            manager: $manager,
            exclusionMatcher: new MaintenanceExclusionMatcher(),
            twig: null,
            template: 'x',
            bypassToken: 'secret-bypass',
            bypassQueryParameter: 'maintenance_bypass',
            bypassCookieName: 'nowo_maintenance_bypass',
            bypassSetCookie: true,
        );
        $kernel = $this->createMock(HttpKernelInterface::class);

        // Request 1: maintenance off → no 503
        $event1 = new RequestEvent($kernel, Request::create('/page'), HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event1);
        self::assertNull($event1->getResponse());

        // Mutate state (CLI / other worker / panel) without resetting the subscriber
        $manager->enable('Worker window', 'ops');

        // Request 2: same subscriber instance must re-read storage → 503
        $event2 = new RequestEvent($kernel, Request::create('/page'), HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event2);
        $response2 = $event2->getResponse();
        self::assertInstanceOf(Response::class, $response2);
        self::assertSame(503, $response2->getStatusCode());
        self::assertStringContainsString('Worker window', (string) $response2->getContent());

        $manager->disable('ops');

        // Request 3: after disable, same instance must allow traffic again
        $event3 = new RequestEvent($kernel, Request::create('/page'), HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($event3);
        self::assertNull($event3->getResponse());
    }

    public function testBypassCookieAttributeDoesNotLeakToNextRequest(): void
    {
        $stateStorage = new class implements MaintenanceStateStorageInterface {
            public function load(): MaintenanceState
            {
                return (new MaintenanceState())->withEnabled(true)->withMessage('Down');
            }

            public function save(MaintenanceState $state): void
            {
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

        $subscriber = new MaintenanceRequestSubscriber(
            enabled: true,
            manager: new MaintenanceManager($stateStorage, $historyStorage),
            exclusionMatcher: new MaintenanceExclusionMatcher(),
            twig: null,
            template: 'x',
            bypassToken: 'token-a',
            bypassQueryParameter: 'maintenance_bypass',
            bypassCookieName: 'nowo_maintenance_bypass',
            bypassSetCookie: true,
        );
        $kernel = $this->createMock(HttpKernelInterface::class);

        $requestWithBypass = Request::create('/page', 'GET', ['maintenance_bypass' => 'token-a']);
        $reqEvent          = new RequestEvent($kernel, $requestWithBypass, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($reqEvent);
        self::assertNull($reqEvent->getResponse());

        $okResponse = new Response('ok');
        $resEvent   = new ResponseEvent($kernel, $requestWithBypass, HttpKernelInterface::MAIN_REQUEST, $okResponse);
        $subscriber->onKernelResponse($resEvent);
        self::assertNotEmpty($okResponse->headers->getCookies());

        // Next request on the same worker, no bypass → 503 and no cookie attribute leftover
        $plainRequest = Request::create('/other');
        $plainEvent   = new RequestEvent($kernel, $plainRequest, HttpKernelInterface::MAIN_REQUEST);
        $subscriber->onKernelRequest($plainEvent);
        self::assertSame(503, $plainEvent->getResponse()?->getStatusCode());
        self::assertFalse($plainRequest->attributes->has('_nowo_maintenance_bypass_cookie'));

        $plainResponse = new Response('blocked');
        $plainResEvent = new ResponseEvent($kernel, $plainRequest, HttpKernelInterface::MAIN_REQUEST, $plainResponse);
        $subscriber->onKernelResponse($plainResEvent);
        self::assertSame([], $plainResponse->headers->getCookies());
    }

    public function testPasswordGateUsesPerRequestSessionOnly(): void
    {
        $gate = new PasswordMaintenanceAccessGate(
            passwordHash: password_hash('secret', PASSWORD_BCRYPT),
            enabled: true,
        );

        $sessionA = new Session(new MockArraySessionStorage());
        $requestA = Request::create('/_maintenance');
        $requestA->setSession($sessionA);
        self::assertTrue($gate->authenticate($requestA, 'secret'));
        self::assertTrue($gate->isGranted($requestA));

        $sessionB = new Session(new MockArraySessionStorage());
        $requestB = Request::create('/_maintenance');
        $requestB->setSession($sessionB);
        self::assertFalse($gate->isGranted($requestB));
    }
}
