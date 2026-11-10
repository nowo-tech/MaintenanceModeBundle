<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Security;

use Nowo\MaintenanceModeBundle\Security\ActorResolverInterface;
use Nowo\MaintenanceModeBundle\Security\SecurityTokenActorResolver;
use Nowo\MaintenanceModeBundle\Security\StaticActorResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class ActorResolverTest extends TestCase
{
    public function testStaticResolverDefaultsToPanel(): void
    {
        self::assertSame('panel', (new StaticActorResolver())->resolveActor(Request::create('/')));
        self::assertSame(ActorResolverInterface::PANEL, (new StaticActorResolver())->resolveActor(Request::create('/')));
        self::assertSame('ops-panel', (new StaticActorResolver('ops-panel'))->resolveActor(Request::create('/')));
        self::assertNull((new StaticActorResolver(null))->resolveActor(Request::create('/')));
    }

    public function testTokenResolverReturnsUserIdentifier(): void
    {
        $storage = new TokenStorage();
        $storage->setToken(new UsernamePasswordToken(new InMemoryUser('alice@example.test', null, ['ROLE_ADMIN']), 'main', ['ROLE_ADMIN']));

        self::assertSame('alice@example.test', (new SecurityTokenActorResolver($storage))->resolveActor(Request::create('/')));
    }

    public function testTokenResolverFallsBackWithoutToken(): void
    {
        self::assertSame('panel', (new SecurityTokenActorResolver(new TokenStorage()))->resolveActor(Request::create('/')));
        self::assertSame('panel', (new SecurityTokenActorResolver())->resolveActor(Request::create('/')));
        self::assertSame('anon', (new SecurityTokenActorResolver(null, 'anon'))->resolveActor(Request::create('/')));
    }
}
