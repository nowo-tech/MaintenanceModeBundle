<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Security\Http;

use Nowo\MaintenanceModeBundle\Security\Http\MaintenanceAccessContextFactory;
use Nowo\MaintenanceModeBundle\Security\Http\SymfonyMaintenanceAccessSession;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class MaintenanceAccessContextFactoryTest extends TestCase
{
    /** @var array<string> */
    private array $trustedProxies = [];

    private int $trustedHeaders = 0;

    protected function setUp(): void
    {
        $this->trustedProxies = Request::getTrustedProxies();
        $this->trustedHeaders = Request::getTrustedHeaderSet();
    }

    protected function tearDown(): void
    {
        // Restores the global trusted-proxy state; Symfony's own default header set is -1 (outside the documented range).
        Request::setTrustedProxies($this->trustedProxies, $this->trustedHeaders); // @phpstan-ignore argument.type
    }

    public function testCopiesPathMethodIpAndOnlyAllowListedHeaders(): void
    {
        $request = Request::create('/_maintenance/login?x=1', 'POST', server: [
            'REMOTE_ADDR'          => '198.51.100.7',
            'HTTP_USER_AGENT'      => 'probe/1.0',
            'HTTP_ACCEPT_LANGUAGE' => 'es',
            'HTTP_COOKIE'          => 'a=b',
            'HTTP_AUTHORIZATION'   => 'Bearer REDACTED-TEST-x',
        ]);

        $context = MaintenanceAccessContextFactory::fromRequest($request);

        self::assertSame('/_maintenance/login', $context->path);
        self::assertSame('POST', $context->method);
        self::assertSame('198.51.100.7', $context->clientIp);
        self::assertSame(['user-agent' => 'probe/1.0', 'accept-language' => 'es'], $context->headers);
        self::assertNull($context->header('cookie'));
        self::assertNull($context->header('authorization'));
        self::assertNull($context->session);
        self::assertNull($context->userIdentifier);
        self::assertSame([], $context->roles);
    }

    public function testUntrustedForwardedForIsIgnoredExactlyAsGetClientIp(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        $request = Request::create('/', server: ['REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.1']);

        $context = MaintenanceAccessContextFactory::fromRequest($request);

        self::assertSame('198.51.100.7', $context->clientIp);
        self::assertSame($request->getClientIp(), $context->clientIp);
    }

    public function testTrustedProxyForwardedForIsHonouredExactlyAsGetClientIp(): void
    {
        Request::setTrustedProxies(['198.51.100.7'], Request::HEADER_X_FORWARDED_FOR);
        $request = Request::create('/', server: ['REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.1']);

        $context = MaintenanceAccessContextFactory::fromRequest($request);

        self::assertSame('203.0.113.1', $context->clientIp);
        self::assertSame($request->getClientIp(), $context->clientIp);
    }

    public function testUserIdentifierAndRolesComeFromTheToken(): void
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken(new InMemoryUser('alice', null, ['ROLE_OPS']), 'main', ['ROLE_OPS']));

        $context = MaintenanceAccessContextFactory::fromRequest(Request::create('/'), $storage);

        self::assertSame('alice', $context->userIdentifier);
        self::assertSame(['ROLE_OPS'], $context->roles);
        self::assertNull(MaintenanceAccessContextFactory::fromRequest(Request::create('/'), new TokenStorage())->userIdentifier);
    }

    public function testSessionAdapterReadsAndWritesTheRequestSession(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->set('k', 'v');
        $request = Request::create('/');
        $request->setSession($session);

        $adapter = MaintenanceAccessContextFactory::fromRequest($request)->session;

        self::assertInstanceOf(SymfonyMaintenanceAccessSession::class, $adapter);
        self::assertSame('v', $adapter->get('k'));
        self::assertSame('d', $adapter->get('missing', 'd'));
        $adapter->set('n', 1);
        self::assertSame(1, $session->get('n'));
        $adapter->remove('k');
        self::assertFalse($session->has('k'));
        $id = $session->getId();
        $adapter->migrate(true);
        self::assertNotSame($id, $session->getId());
        self::assertSame(1, $session->get('n'));
    }
}
