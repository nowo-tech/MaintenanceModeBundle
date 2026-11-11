<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Controller;

use Nowo\MaintenanceModeBundle\Controller\MaintenancePanelController;
use Nowo\MaintenanceModeBundle\Panel\PanelView;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessContext;
use Nowo\MaintenanceModeBundle\Security\MaintenanceActorResolverInterface;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Tests\Support\HostPorts\RecordingContextGate;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryHistoryStorage;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryStateStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Csrf\CsrfExtension;
use Symfony\Component\Form\Extension\HttpFoundation\HttpFoundationExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

use function array_map;

/**
 * The panel drives a gate / actor resolver that implement only the framework-free ports (DDD host path).
 */
final class MaintenancePanelControllerContextPortsTest extends TestCase
{
    private InMemoryStateStorage $stateStorage;

    private InMemoryHistoryStorage $historyStorage;

    private RecordingContextGate $gate;

    private TokenStorage $tokens;

    protected function setUp(): void
    {
        $this->stateStorage   = new InMemoryStateStorage();
        $this->historyStorage = new InMemoryHistoryStorage();
        $this->gate           = new RecordingContextGate();
        $this->tokens         = new TokenStorage();
        $this->tokens->setToken(new UsernamePasswordToken(new InMemoryUser('ops-1', null, ['ROLE_OPS']), 'main', ['ROLE_OPS']));
    }

    public function testDeniedRedirectsToLoginAndTheGateSeesTheRequestContext(): void
    {
        $response = $this->controller()->index(Request::create('/_maintenance', server: ['REMOTE_ADDR' => '198.51.100.3', 'HTTP_USER_AGENT' => 'probe']));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/_maintenance/login', $response->getTargetUrl());
        $context = $this->gate->contexts[0];
        self::assertSame(['granted', '/_maintenance', 'GET', '198.51.100.3', 'probe', 'ops-1', ['ROLE_OPS']], [
            $context[0], $context[1]->path, $context[1]->method, $context[1]->clientIp, $context[1]->header('user-agent'),
            $context[1]->userIdentifier, $context[1]->roles,
        ]);
    }

    public function testGrantedRendersThePanel(): void
    {
        $this->gate->granted = true;

        self::assertSame(Response::HTTP_OK, $this->controller()->index(Request::create('/_maintenance'))->getStatusCode());
    }

    public function testLoginUsesLockoutThenAuthenticate(): void
    {
        $this->gate->passwordRequired = true;

        $response = $this->controller()->login($this->loginPost());
        self::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        self::assertSame(['granted', 'locked', 'auth'], $this->ops());

        $this->gate->authResult = true;
        $response               = $this->controller()->login($this->loginPost());
        self::assertInstanceOf(RedirectResponse::class, $response);

        $this->gate->contexts = [];
        $this->gate->locked   = true;
        $response             = $this->controller()->login($this->loginPost());
        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
        self::assertSame(['granted', 'locked'], $this->ops(), 'Locked out: the password is not checked.');
    }

    public function testLogoutRevokesThroughTheContextPort(): void
    {
        $response = $this->controller()->logout(Request::create('/_maintenance/logout', 'POST', ['confirmed' => '1', '_token' => 'valid']));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(['revoke'], $this->ops());
    }

    public function testActorComesFromTheContextResolver(): void
    {
        $this->gate->granted = true;
        $resolver            = new class implements MaintenanceActorResolverInterface {
            public function resolveActorFor(MaintenanceAccessContext $context): string
            {
                return 'ctx:' . $context->userIdentifier;
            }
        };

        $this->controller($resolver)->enable(Request::create('/_maintenance/enable', 'POST', ['message' => 'x', '_token' => 'valid']));

        self::assertSame('ctx:ops-1', $this->stateStorage->state->getUpdatedBy());
        self::assertSame('ctx:ops-1', $this->historyStorage->entries[0]->getActor());
    }

    /**
     * @return list<string>
     */
    private function ops(): array
    {
        return array_map(static fn (array $c): string => $c[0], $this->gate->contexts);
    }

    private function loginPost(): Request
    {
        return Request::create('/_maintenance/login', 'POST', ['password' => 'x', '_token' => 'valid']);
    }

    private function controller(?MaintenanceActorResolverInterface $resolver = null): MaintenancePanelController
    {
        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('getToken')->willReturnCallback(static fn (string $id): CsrfToken => new CsrfToken($id, 'valid'));
        $csrf->method('isTokenValid')->willReturn(true);
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<html>ok</html>');
        $forms = Forms::createFormFactoryBuilder()
            ->addExtension(new HttpFoundationExtension())
            ->addExtension(new CsrfExtension($csrf))
            ->getFormFactory();

        return new MaintenancePanelController(
            new MaintenanceManager($this->stateStorage, $this->historyStorage),
            $this->gate,
            $forms,
            new PanelView($twig),
            $csrf,
            actorResolver: $resolver,
            tokenStorage: $this->tokens,
        );
    }
}
