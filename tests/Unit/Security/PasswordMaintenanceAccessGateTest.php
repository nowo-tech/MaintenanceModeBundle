<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Security;

use Nowo\MaintenanceModeBundle\Security\PasswordMaintenanceAccessGate;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

use function password_hash;

use const PASSWORD_BCRYPT;

final class PasswordMaintenanceAccessGateTest extends TestCase
{
    public function testOpenWhenNoHashConfigured(): void
    {
        $gate    = new PasswordMaintenanceAccessGate(null, true);
        $request = Request::create('/');

        self::assertFalse($gate->isPasswordRequired());
        self::assertTrue($gate->isGranted($request));
    }

    public function testPasswordVerifyWithSession(): void
    {
        $hash    = password_hash('secret', PASSWORD_BCRYPT);
        $gate    = new PasswordMaintenanceAccessGate($hash, true);
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        self::assertTrue($gate->isPasswordRequired());
        self::assertFalse($gate->isGranted($request));
        self::assertFalse($gate->authenticate($request, 'wrong'));
        self::assertTrue($gate->authenticate($request, 'secret'));
        self::assertTrue($gate->isGranted($request));
        $gate->revoke($request);
        self::assertFalse($gate->isGranted($request));
    }

    public function testPasswordProtectionDisabledAllowsAccessDespiteHash(): void
    {
        $hash    = password_hash('secret', PASSWORD_BCRYPT);
        $gate    = new PasswordMaintenanceAccessGate($hash, false);
        $request = Request::create('/');

        self::assertFalse($gate->isPasswordRequired());
        self::assertTrue($gate->isGranted($request));
        self::assertTrue($gate->authenticate($request, 'anything'));
    }

    public function testGrantedFalseWhenSessionMissing(): void
    {
        $hash    = password_hash('secret', PASSWORD_BCRYPT);
        $gate    = new PasswordMaintenanceAccessGate($hash, true);
        $request = Request::create('/');

        self::assertFalse($gate->isGranted($request));
    }

    public function testAuthenticateWithoutSessionStillReturnsTrueOnSuccess(): void
    {
        $hash    = password_hash('secret', PASSWORD_BCRYPT);
        $gate    = new PasswordMaintenanceAccessGate($hash, true);
        $request = Request::create('/');

        self::assertTrue($gate->authenticate($request, 'secret'));
    }

    public function testRevokeWithoutSessionIsNoOp(): void
    {
        $hash    = password_hash('secret', PASSWORD_BCRYPT);
        $gate    = new PasswordMaintenanceAccessGate($hash, true);
        $request = Request::create('/');

        $gate->revoke($request);
        self::assertFalse($gate->isGranted($request));
    }
}
