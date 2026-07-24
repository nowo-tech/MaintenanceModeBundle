<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Controller;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessGateInterface;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryHistoryStorage;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryStateStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

use function count;

final class MaintenancePanelControllerTest extends TestCase
{
    private const TEMPLATES = [
        'page'          => '@NowoMaintenanceModeBundle/maintenance/page.html.twig',
        'panel_layout'  => '@NowoMaintenanceModeBundle/panel/layout.html.twig',
        'panel_index'   => '@NowoMaintenanceModeBundle/panel/index.html.twig',
        'panel_login'   => '@NowoMaintenanceModeBundle/panel/login.html.twig',
        'panel_history' => '@NowoMaintenanceModeBundle/panel/history.html.twig',
    ];

    private InMemoryStateStorage $stateStorage;

    private InMemoryHistoryStorage $historyStorage;

    private MaintenanceManager $manager;

    private MaintenanceAccessGateInterface&MockObject $accessGate;

    private Environment&MockObject $twig;

    private CsrfTokenManagerInterface&MockObject $csrfTokenManager;

    protected function setUp(): void
    {
        $this->stateStorage     = new InMemoryStateStorage();
        $this->historyStorage   = new InMemoryHistoryStorage();
        $this->manager          = new MaintenanceManager($this->stateStorage, $this->historyStorage);
        $this->accessGate       = $this->createMock(MaintenanceAccessGateInterface::class);
        $this->twig             = $this->createMock(Environment::class);
        $this->csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
    }

    public function testIndexRendersPanelWhenGranted(): void
    {
        $this->accessGate->method('isGranted')->willReturn(true);
        $this->twig->expects(self::once())
            ->method('render')
            ->with(self::TEMPLATES['panel_index'], self::callback(static function (array $context): bool {
                return isset($context['state'], $context['path_prefix'], $context['layout'])
                    && $context['path_prefix'] === '/_maintenance';
            }))
            ->willReturn('<html>index</html>');

        $response = $this->createController()->index(Request::create('/_maintenance'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>index</html>', $response->getContent());
    }

    public function testIndexRedirectsToLoginWhenDenied(): void
    {
        $this->accessGate->method('isGranted')->willReturn(false);

        $response = $this->createController()->index(Request::create('/_maintenance'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/_maintenance/login', $response->getTargetUrl());
    }

    public function testEnableCallsManagerAndRedirects(): void
    {
        $this->accessGate->method('isGranted')->willReturn(true);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $request = Request::create('/_maintenance/enable', 'POST', [
            'message' => 'Going down',
            '_token'  => 'valid',
        ]);
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $response = $this->createController()->enable($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/_maintenance', $response->getTargetUrl());
        self::assertTrue($this->stateStorage->state->isEnabled());
        self::assertSame('Going down', $this->stateStorage->state->getMessage());
        self::assertSame(['panel.flash.enabled'], $session->getFlashBag()->peek('success'));
    }

    public function testEnableWithEmptyMessageUsesDefault(): void
    {
        $this->accessGate->method('isGranted')->willReturn(true);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $request = Request::create('/_maintenance/enable', 'POST', [
            'message' => '',
            '_token'  => 'valid',
        ]);

        $this->createController(defaultMessage: 'Fallback')->enable($request);

        self::assertSame('Fallback', $this->stateStorage->state->getMessage());
    }

    public function testEnableReturns403OnInvalidCsrf(): void
    {
        $this->accessGate->method('isGranted')->willReturn(true);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(false);

        $request  = Request::create('/_maintenance/enable', 'POST', ['_token' => 'bad']);
        $response = $this->createController()->enable($request);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertSame('Invalid CSRF token.', $response->getContent());
    }

    public function testDisableCallsManagerAndRedirects(): void
    {
        $this->stateStorage->state = (new MaintenanceState())->withEnabled(true);
        $this->accessGate->method('isGranted')->willReturn(true);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $request  = Request::create('/_maintenance/disable', 'POST', ['_token' => 'valid']);
        $response = $this->createController()->disable($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertFalse($this->stateStorage->state->isEnabled());
    }

    public function testDisableReturns403OnInvalidCsrf(): void
    {
        $this->accessGate->method('isGranted')->willReturn(true);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(false);

        $response = $this->createController()->disable(
            Request::create('/_maintenance/disable', 'POST', ['_token' => 'bad']),
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testEnableRedirectsToLoginWhenDenied(): void
    {
        $this->accessGate->method('isGranted')->willReturn(false);

        $response = $this->createController()->enable(
            Request::create('/_maintenance/enable', 'POST', ['_token' => 'x']),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/_maintenance/login', $response->getTargetUrl());
    }

    public function testDisableRedirectsToLoginWhenDenied(): void
    {
        $this->accessGate->method('isGranted')->willReturn(false);

        $response = $this->createController()->disable(
            Request::create('/_maintenance/disable', 'POST'),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/_maintenance/login', $response->getTargetUrl());
    }

    public function testScheduleRedirectsToLoginWhenDenied(): void
    {
        $this->accessGate->method('isGranted')->willReturn(false);

        $response = $this->createController()->schedule(
            Request::create('/_maintenance/schedule', 'POST'),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/_maintenance/login', $response->getTargetUrl());
    }

    public function testHistoryRedirectsToLoginWhenDenied(): void
    {
        $this->accessGate->method('isGranted')->willReturn(false);

        $response = $this->createController()->history(Request::create('/_maintenance/history'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/_maintenance/login', $response->getTargetUrl());
    }

    public function testScheduleUpdatesStateAndRedirects(): void
    {
        $this->accessGate->method('isGranted')->willReturn(true);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $request = Request::create('/_maintenance/schedule', 'POST', [
            'scheduled_enable_at'  => '2026-07-25T08:00:00+00:00',
            'scheduled_disable_at' => '2026-07-25T18:00:00+00:00',
            'message'              => 'Planned',
            '_token'               => 'valid',
        ]);

        $response = $this->createController()->schedule($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('Planned', $this->stateStorage->state->getMessage());
        self::assertNotNull($this->stateStorage->state->getScheduledEnableAt());
        self::assertNotNull($this->stateStorage->state->getScheduledDisableAt());
        self::assertCount(1, $this->historyStorage->entries);
        self::assertSame('schedule', $this->historyStorage->entries[0]->getAction());
    }

    public function testScheduleReturns403OnInvalidCsrf(): void
    {
        $this->accessGate->method('isGranted')->willReturn(true);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(false);

        $response = $this->createController()->schedule(
            Request::create('/_maintenance/schedule', 'POST', ['_token' => 'bad']),
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testClearScheduleClearsTimestampsAndRedirects(): void
    {
        $this->stateStorage->state = (new MaintenanceState())
            ->withScheduledEnableAt(new DateTimeImmutable('2026-07-25T08:00:00+00:00'))
            ->withScheduledDisableAt(new DateTimeImmutable('2026-07-25T18:00:00+00:00'));
        $this->accessGate->method('isGranted')->willReturn(true);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $request  = Request::create('/_maintenance/clear-schedule', 'POST', ['_token' => 'valid']);
        $response = $this->createController()->clearSchedule($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertNull($this->stateStorage->state->getScheduledEnableAt());
        self::assertNull($this->stateStorage->state->getScheduledDisableAt());
        self::assertSame('clear_schedule', $this->historyStorage->entries[0]->getAction());
    }

    public function testClearScheduleReturns403OnInvalidCsrf(): void
    {
        $this->accessGate->method('isGranted')->willReturn(true);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(false);

        $response = $this->createController()->clearSchedule(
            Request::create('/_maintenance/clear-schedule', 'POST', ['_token' => 'bad']),
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testClearScheduleRedirectsToLoginWhenDenied(): void
    {
        $this->accessGate->method('isGranted')->willReturn(false);

        $response = $this->createController()->clearSchedule(
            Request::create('/_maintenance/clear-schedule', 'POST'),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/_maintenance/login', $response->getTargetUrl());
    }

    public function testIndexPassesFlashesAndPasswordRequired(): void
    {
        $this->accessGate->method('isGranted')->willReturn(true);
        $this->accessGate->method('isPasswordRequired')->willReturn(false);

        $request = Request::create('/_maintenance');
        $session = new Session(new MockArraySessionStorage());
        $session->getFlashBag()->add('success', 'panel.flash.enabled');
        $request->setSession($session);

        $this->twig->expects(self::once())
            ->method('render')
            ->with(self::TEMPLATES['panel_index'], self::callback(static function (array $context): bool {
                return ($context['password_required'] ?? null) === false
                    && ($context['flashes']['success'][0] ?? null) === 'panel.flash.enabled';
            }))
            ->willReturn('<html>index</html>');

        $this->createController()->index($request);
    }

    public function testHistoryRendersEntries(): void
    {
        $this->accessGate->method('isGranted')->willReturn(true);
        $this->historyStorage->entries = [
            new MaintenanceHistoryEntry('enable', new DateTimeImmutable(), 'panel'),
        ];

        $this->twig->expects(self::once())
            ->method('render')
            ->with(self::TEMPLATES['panel_history'], self::callback(static function (array $context): bool {
                return count($context['entries']) === 1 && $context['path_prefix'] === '/_maintenance';
            }))
            ->willReturn('<html>history</html>');

        $response = $this->createController()->history(Request::create('/_maintenance/history'));

        self::assertSame('<html>history</html>', $response->getContent());
    }

    public function testLoginRedirectsWhenAlreadyGranted(): void
    {
        $this->accessGate->method('isPasswordRequired')->willReturn(true);
        $this->accessGate->method('isGranted')->willReturn(true);

        $response = $this->createController()->login(Request::create('/_maintenance/login'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/_maintenance', $response->getTargetUrl());
    }

    public function testLoginRedirectsWhenPasswordNotRequired(): void
    {
        $this->accessGate->method('isPasswordRequired')->willReturn(false);

        $response = $this->createController()->login(Request::create('/_maintenance/login'));

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    public function testLoginGetRendersForm(): void
    {
        $this->accessGate->method('isPasswordRequired')->willReturn(true);
        $this->accessGate->method('isGranted')->willReturn(false);

        $this->twig->expects(self::once())
            ->method('render')
            ->with(self::TEMPLATES['panel_login'], self::callback(static fn (array $c): bool => $c['error'] === null))
            ->willReturn('<html>login</html>');

        $response = $this->createController()->login(Request::create('/_maintenance/login', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<html>login</html>', $response->getContent());
    }

    public function testLoginPostSuccessRedirects(): void
    {
        $this->accessGate->method('isPasswordRequired')->willReturn(true);
        $this->accessGate->method('isGranted')->willReturn(false);
        $this->accessGate->method('authenticate')->with(self::anything(), 'secret')->willReturn(true);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $request = Request::create('/_maintenance/login', 'POST', [
            'password' => 'secret',
            '_token'   => 'valid',
        ]);

        $response = $this->createController()->login($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/_maintenance', $response->getTargetUrl());
    }

    public function testLoginPostFailureRendersWithError(): void
    {
        $this->accessGate->method('isPasswordRequired')->willReturn(true);
        $this->accessGate->method('isGranted')->willReturn(false);
        $this->accessGate->method('authenticate')->willReturn(false);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);

        $this->twig->expects(self::once())
            ->method('render')
            ->with(self::TEMPLATES['panel_login'], self::callback(static fn (array $c): bool => $c['error'] === 'Invalid password.'))
            ->willReturn('<html>login-error</html>');

        $request = Request::create('/_maintenance/login', 'POST', [
            'password' => 'wrong',
            '_token'   => 'valid',
        ]);

        $response = $this->createController()->login($request);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testLoginPostReturns403OnInvalidCsrf(): void
    {
        $this->accessGate->method('isPasswordRequired')->willReturn(true);
        $this->accessGate->method('isGranted')->willReturn(false);
        $this->csrfTokenManager->method('isTokenValid')->willReturn(false);

        $response = $this->createController()->login(
            Request::create('/_maintenance/login', 'POST', ['_token' => 'bad']),
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testLogoutRevokesAndRedirectsToLogin(): void
    {
        $this->csrfTokenManager->method('isTokenValid')->willReturn(true);
        $this->accessGate->expects(self::once())->method('revoke');

        $request  = Request::create('/_maintenance/logout', 'POST', ['_token' => 'valid']);
        $response = $this->createController()->logout($request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/_maintenance/login', $response->getTargetUrl());
    }

    public function testLogoutReturns403OnInvalidCsrf(): void
    {
        $this->csrfTokenManager->method('isTokenValid')->willReturn(false);

        $response = $this->createController()->logout(
            Request::create('/_maintenance/logout', 'POST', ['_token' => 'bad']),
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testCsrfSkippedWhenManagerNull(): void
    {
        $this->accessGate->method('isGranted')->willReturn(true);

        $controller = new MaintenancePanelController(
            $this->manager,
            $this->accessGate,
            $this->twig,
            self::TEMPLATES,
            '/_maintenance',
        );

        $response = $controller->enable(
            Request::create('/_maintenance/enable', 'POST', ['message' => 'No CSRF']),
        );

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertTrue($this->stateStorage->state->isEnabled());
    }

    public function testCsrfTokenValidatedWithExpectedId(): void
    {
        $this->accessGate->method('isGranted')->willReturn(true);
        $this->csrfTokenManager->expects(self::once())
            ->method('isTokenValid')
            ->with(self::callback(static fn (CsrfToken $token): bool => $token->getId() === MaintenancePanelController::CSRF_ENABLE
                && $token->getValue() === 'tok'))
            ->willReturn(true);

        $this->createController()->enable(
            Request::create('/_maintenance/enable', 'POST', ['_token' => 'tok']),
        );
    }

    private function createController(?string $defaultMessage = null): MaintenancePanelController
    {
        $manager = $defaultMessage !== null
            ? new MaintenanceManager($this->stateStorage, $this->historyStorage, $defaultMessage)
            : $this->manager;

        return new MaintenancePanelController(
            $manager,
            $this->accessGate,
            $this->twig,
            self::TEMPLATES,
            '/_maintenance',
            $this->csrfTokenManager,
        );
    }
}
