<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Security;

use Nowo\MaintenanceModeBundle\Security\PasswordMaintenanceAccessGate;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

use function password_hash;
use function time;

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

    public function testSuccessfulLoginMigratesSessionId(): void
    {
        $hash    = password_hash('secret', PASSWORD_BCRYPT);
        $gate    = new PasswordMaintenanceAccessGate($hash, true);
        $session = new Session(new MockArraySessionStorage());
        $session->setId('attacker-fixed-session-id');
        $session->start();
        $request = Request::create('/');
        $request->setSession($session);

        self::assertFalse($gate->authenticate($request, 'wrong'));
        self::assertSame('attacker-fixed-session-id', $session->getId());

        self::assertTrue($gate->authenticate($request, 'secret'));
        self::assertNotSame('attacker-fixed-session-id', $session->getId());
        self::assertTrue($gate->isGranted($request));
        self::assertFalse($session->has(PasswordMaintenanceAccessGate::SESSION_FAILURES_KEY));
    }

    public function testSessionLockoutAfterMaxFailedAttemptsEvenWithCorrectPassword(): void
    {
        $hash    = password_hash('secret', PASSWORD_BCRYPT);
        $gate    = new PasswordMaintenanceAccessGate($hash, true, 3, 900);
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        for ($i = 0; $i < 3; ++$i) {
            self::assertFalse($gate->isLockedOut($request));
            self::assertFalse($gate->authenticate($request, 'guess-' . $i));
        }

        self::assertTrue($gate->isLockedOut($request));
        self::assertFalse($gate->authenticate($request, 'secret'));
        self::assertFalse($gate->isGranted($request));
    }

    public function testExpiredFailuresDoNotCount(): void
    {
        $hash    = password_hash('secret', PASSWORD_BCRYPT);
        $gate    = new PasswordMaintenanceAccessGate($hash, true, 2, 60);
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->getSession()->set(PasswordMaintenanceAccessGate::SESSION_FAILURES_KEY, [time() - 120, time() - 61, 'junk']);

        self::assertFalse($gate->isLockedOut($request));
        self::assertTrue($gate->authenticate($request, 'secret'));

        $request->getSession()->set(PasswordMaintenanceAccessGate::SESSION_FAILURES_KEY, 'not-an-array');
        self::assertFalse($gate->isLockedOut($request));
    }

    public function testCacheLockoutPerClientIpSurvivesNewSessions(): void
    {
        $hash  = password_hash('secret', PASSWORD_BCRYPT);
        $cache = new ArrayAdapter();
        $gate  = new PasswordMaintenanceAccessGate($hash, true, 2, 900, $cache);

        for ($i = 0; $i < 2; ++$i) {
            // Attacker drops the session cookie on every attempt.
            $request = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7']);
            $request->setSession(new Session(new MockArraySessionStorage()));
            self::assertFalse($gate->authenticate($request, 'guess-' . $i));
        }

        $fresh = Request::create('/', server: ['REMOTE_ADDR' => '203.0.113.7']);
        $fresh->setSession(new Session(new MockArraySessionStorage()));
        self::assertTrue($gate->isLockedOut($fresh));
        self::assertFalse($gate->authenticate($fresh, 'secret'));

        $other = Request::create('/', server: ['REMOTE_ADDR' => '198.51.100.1']);
        $other->setSession(new Session(new MockArraySessionStorage()));
        self::assertFalse($gate->isLockedOut($other));
        self::assertTrue($gate->authenticate($other, 'secret'));
    }

    public function testSuccessfulLoginClearsCacheFailures(): void
    {
        $hash  = password_hash('secret', PASSWORD_BCRYPT);
        $cache = new ArrayAdapter();
        $gate  = new PasswordMaintenanceAccessGate($hash, true, 2, 900, $cache);

        $request = Request::create('/');
        self::assertFalse($gate->authenticate($request, 'wrong'));
        self::assertTrue($gate->authenticate($request, 'secret'));
        self::assertFalse($gate->authenticate($request, 'wrong'));
        self::assertFalse($gate->isLockedOut($request));
    }

    public function testZeroMaxAttemptsDisablesThrottling(): void
    {
        $hash    = password_hash('secret', PASSWORD_BCRYPT);
        $gate    = new PasswordMaintenanceAccessGate($hash, true, 0, 900, new ArrayAdapter());
        $request = Request::create('/');

        for ($i = 0; $i < 5; ++$i) {
            self::assertFalse($gate->authenticate($request, 'wrong'));
        }
        self::assertFalse($gate->isLockedOut($request));
        self::assertTrue($gate->authenticate($request, 'secret'));
    }

    public function testIsLockedOutFalseWhenPasswordNotRequired(): void
    {
        self::assertFalse((new PasswordMaintenanceAccessGate(null))->isLockedOut(Request::create('/')));
    }

    public function testBrokenCachePoolDoesNotBreakLogin(): void
    {
        $hash  = password_hash('secret', PASSWORD_BCRYPT);
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willThrowException(new RuntimeException('down'));
        $cache->method('deleteItem')->willThrowException(new RuntimeException('down'));
        $gate    = new PasswordMaintenanceAccessGate($hash, true, 2, 900, $cache);
        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        self::assertFalse($gate->authenticate($request, 'wrong'));
        self::assertFalse($gate->isLockedOut($request));
        self::assertTrue($gate->authenticate($request, 'secret'));
    }
}
