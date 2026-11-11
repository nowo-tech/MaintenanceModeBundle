<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Security;

use Nowo\MaintenanceModeBundle\Security\Http\SymfonyMaintenanceAccessSession;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class MaintenanceAccessContextTest extends TestCase
{
    public function testDefaultsDescribeAnAnonymousRequestWithoutSession(): void
    {
        $context = new MaintenanceAccessContext('/_maintenance');

        self::assertSame('/_maintenance', $context->path);
        self::assertSame('GET', $context->method);
        self::assertNull($context->clientIp);
        self::assertSame([], $context->headers);
        self::assertNull($context->userIdentifier);
        self::assertSame([], $context->roles);
        self::assertNull($context->session);
        self::assertFalse($context->isAuthenticated());
        self::assertNull($context->header('user-agent'));
    }

    public function testHeadersAreCaseInsensitiveAndRolesAreAList(): void
    {
        $context = new MaintenanceAccessContext(
            '/x',
            'POST',
            '203.0.113.9',
            ['User-Agent' => 'probe/1.0'],
            'alice',
            [3 => 'ROLE_ADMIN', 7 => 'ROLE_OPS'],
        );

        self::assertSame(['user-agent' => 'probe/1.0'], $context->headers);
        self::assertSame('probe/1.0', $context->header('USER-AGENT'));
        self::assertSame(['ROLE_ADMIN', 'ROLE_OPS'], $context->roles);
        self::assertTrue($context->hasRole('ROLE_OPS'));
        self::assertFalse($context->hasRole('role_ops'));
        self::assertTrue($context->isAuthenticated());
        self::assertSame('203.0.113.9', $context->clientIp);
    }

    public function testEmptyUserIdentifierMeansAnonymous(): void
    {
        $context = new MaintenanceAccessContext('/', userIdentifier: '');

        self::assertNull($context->userIdentifier);
        self::assertFalse($context->isAuthenticated());
    }

    public function testDebugOutputMasksTheIpAndLeavesOutHeaderValuesAndTheSession(): void
    {
        $session = new SymfonyMaintenanceAccessSession(new Session(new MockArraySessionStorage()));
        $session->set('nowo_maintenance_mode.panel_granted', true);
        $context = new MaintenanceAccessContext('/_maintenance', 'POST', '203.0.113.9', ['User-Agent' => 'probe/1.0'], 'alice', ['ROLE_OPS'], $session);

        self::assertSame([
            'path'           => '/_maintenance',
            'method'         => 'POST',
            'clientIp'       => '[masked]',
            'headers'        => ['user-agent'],
            'userIdentifier' => 'alice',
            'roles'          => ['ROLE_OPS'],
            'session'        => '[redacted]',
        ], $context->__debugInfo());
        self::assertSame(['session' => '[redacted]'], $session->__debugInfo());

        $dump = print_r($context, true) . print_r($session, true);
        self::assertStringNotContainsString('203.0.113.9', $dump);
        self::assertStringNotContainsString('probe/1.0', $dump);
        self::assertStringNotContainsString('panel_granted', $dump);

        $anonymous = (new MaintenanceAccessContext('/x'))->__debugInfo();
        self::assertNull($anonymous['clientIp']);
        self::assertNull($anonymous['session']);
    }
}
