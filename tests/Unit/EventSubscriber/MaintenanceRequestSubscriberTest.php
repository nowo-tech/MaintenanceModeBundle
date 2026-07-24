<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\EventSubscriber;

use Nowo\MaintenanceModeBundle\EventSubscriber\MaintenanceRequestSubscriber;
use Nowo\MaintenanceModeBundle\Exclusion\MaintenanceExclusionMatcher;
use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
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
    }

    public function testRendersViaTwigWhenAvailable(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects(self::once())
            ->method('render')
            ->with('@NowoMaintenanceModeBundle/maintenance/page.html.twig', ['message' => 'Twig message'])
            ->willReturn('<html>maintenance</html>');

        $subscriber = $this->createSubscriber(
            state: (new MaintenanceState())->withEnabled(true)->withMessage('Twig message'),
            twig: $twig,
        );

        $event = $this->createMainRequestEvent('/page');
        $subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        self::assertSame('<html>maintenance</html>', $response?->getContent());
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
            'The site is temporarily unavailable for maintenance.',
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

    public function testGetSubscribedEvents(): void
    {
        $events = MaintenanceRequestSubscriber::getSubscribedEvents();

        self::assertArrayHasKey('kernel.request', $events);
        self::assertSame(['onKernelRequest', 32], $events['kernel.request']);
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
