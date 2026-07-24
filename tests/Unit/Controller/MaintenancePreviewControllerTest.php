<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Controller;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Controller\MaintenancePreviewController;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryHistoryStorage;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryStateStorage;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

final class MaintenancePreviewControllerTest extends TestCase
{
    private InMemoryStateStorage $stateStorage;

    private MaintenanceManager $manager;

    private Environment&MockObject $twig;

    protected function setUp(): void
    {
        $this->stateStorage = new InMemoryStateStorage();
        $this->manager      = new MaintenanceManager($this->stateStorage, new InMemoryHistoryStorage(), 'Default preview');
        $this->twig         = $this->createMock(Environment::class);
    }

    public function testThrowsWhenDisabled(): void
    {
        $controller = $this->createController(enabled: false);

        $this->expectException(NotFoundHttpException::class);
        $controller(Request::create('/_maintenance_preview'));
    }

    public function testRendersConfiguredTemplateWithState(): void
    {
        $this->stateStorage->state = (new MaintenanceState())
            ->withEnabled(true)
            ->withMessage('Live message')
            ->withScheduledDisableAt(new DateTimeImmutable('+1 hour'));

        $this->twig->expects(self::once())
            ->method('render')
            ->with(
                '@NowoMaintenanceModeBundle/maintenance/page.html.twig',
                self::callback(static function (array $ctx): bool {
                    return ($ctx['message'] ?? null) === 'Live message'
                        && ($ctx['state'] ?? null) instanceof MaintenanceState;
                }),
            )
            ->willReturn('<html>preview</html>');

        $response = $this->createController(enabled: true)(Request::create('/_maintenance_preview'));

        self::assertSame(503, $response->getStatusCode());
        self::assertSame('<html>preview</html>', $response->getContent());
        self::assertSame('1', $response->headers->get('X-Maintenance-Preview'));
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function testUsesDefaultMessageWhenStateHasNone(): void
    {
        $this->twig->expects(self::once())
            ->method('render')
            ->with(
                self::anything(),
                self::callback(static fn (array $ctx): bool => ($ctx['message'] ?? null) === 'Default preview'),
            )
            ->willReturn('ok');

        $this->createController(enabled: true)(Request::create('/_maintenance_preview'));
    }

    public function testQueryMessageOverridesState(): void
    {
        $this->stateStorage->state = (new MaintenanceState())->withMessage('Stored');

        $this->twig->expects(self::once())
            ->method('render')
            ->with(
                self::anything(),
                self::callback(static fn (array $ctx): bool => ($ctx['message'] ?? null) === 'Override'),
            )
            ->willReturn('ok');

        $this->createController(enabled: true)(Request::create('/_maintenance_preview', 'GET', ['message' => 'Override']));
    }

    public function testJsonPreview(): void
    {
        $this->stateStorage->state = (new MaintenanceState())->withMessage('JSON preview');

        $request  = Request::create('/_maintenance_preview', 'GET', server: ['HTTP_ACCEPT' => 'application/json']);
        $response = $this->createController(enabled: true)($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertStringContainsString('"preview":true', (string) $response->getContent());
        self::assertStringContainsString('JSON preview', (string) $response->getContent());
    }

    public function testPastScheduleUsesConfiguredRetryAfter(): void
    {
        $this->stateStorage->state = (new MaintenanceState())
            ->withMessage('x')
            ->withScheduledDisableAt(new DateTimeImmutable('-1 minute'));

        $this->twig->method('render')->willReturn('ok');

        $response = $this->createController(enabled: true, retryAfter: 42)(Request::create('/_maintenance_preview'));

        self::assertSame('42', $response->headers->get('Retry-After'));
    }

    private function createController(bool $enabled, int $retryAfter = 3600): MaintenancePreviewController
    {
        return new MaintenancePreviewController(
            enabled: $enabled,
            manager: $this->manager,
            twig: $this->twig,
            template: '@NowoMaintenanceModeBundle/maintenance/page.html.twig',
            defaultMessage: 'Default preview',
            statusCode: 503,
            retryAfter: $retryAfter,
        );
    }
}
