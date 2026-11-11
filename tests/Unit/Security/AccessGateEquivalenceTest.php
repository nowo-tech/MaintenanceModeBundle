<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Security;

use Closure;
use Nowo\MaintenanceModeBundle\Security\Http\MaintenanceAccessContextFactory;
use Nowo\MaintenanceModeBundle\Security\MaintenanceAccessContext;
use Nowo\MaintenanceModeBundle\Security\PasswordMaintenanceAccessGate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

use function hash;
use function password_hash;
use function str_starts_with;

use const PASSWORD_BCRYPT;

/**
 * The access decision of the framework-free port (context methods) equals the deprecated `Request` port for the same
 * requests: same grants, same denials (deny by default without a session), same lockout per session and per client IP
 * (trusted-proxy handling from `Request::getClientIp()`), same session keys and the same cache key.
 */
final class AccessGateEquivalenceTest extends TestCase
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

    /**
     * Hash mode, protection on, steps [op, visitor, password], trusted proxies.
     *
     * @return iterable<string, array{0: string|null, 1: bool, 2: list<array{0: string, 1: string, 2?: string}>, 3?: list<string>}>
     */
    public static function scenarios(): iterable
    {
        $ok  = 'ok-REDACTED-TEST';
        $bad = 'bad-REDACTED-TEST';

        yield 'no hash: open to everyone, even without session' => [null, true, [
            ['granted', 'nosession'], ['granted', 'a'], ['locked', 'a'], ['auth', 'nosession', $bad],
        ]];
        yield 'protection disabled: open' => ['hash', false, [
            ['granted', 'nosession'], ['auth', 'a', $bad], ['granted', 'a'],
        ]];
        yield 'deny by default: no session, fresh session' => ['hash', true, [
            ['granted', 'nosession'], ['granted', 'a'], ['locked', 'a'], ['revoke', 'nosession'], ['granted', 'a'],
        ]];
        yield 'login, grant, revoke' => ['hash', true, [
            ['auth', 'a', $bad], ['granted', 'a'], ['auth', 'a', $ok], ['granted', 'a'], ['granted', 'b'],
            ['revoke', 'a'], ['granted', 'a'],
        ]];
        yield 'no session: a correct password returns true but grants nothing' => ['hash', true, [
            ['auth', 'nosession', $ok], ['granted', 'nosession'],
        ]];
        yield 'lockout per session then correct password refused' => ['hash', true, [
            ['auth', 'a', $bad], ['locked', 'a'], ['auth', 'a', $bad], ['locked', 'a'], ['auth', 'a', $bad],
            ['locked', 'a'], ['auth', 'a', $ok], ['granted', 'a'],
        ]];
        yield 'lockout per client IP across sessions (cache)' => ['hash', true, [
            ['auth', 'a', $bad], ['auth', 'b', $bad], ['auth', 'c', $bad], ['locked', 'd'], ['auth', 'd', $ok],
            ['granted', 'd'], ['locked', 'other-ip'], ['auth', 'other-ip', $ok], ['granted', 'other-ip'],
        ]];
        yield 'untrusted X-Forwarded-For does not split the IP counter' => ['hash', true, [
            ['auth', 'xff1', $bad], ['auth', 'xff2', $bad], ['auth', 'xff3', $bad], ['locked', 'xff4'],
        ]];
        yield 'trusted proxy: the forwarded IP keys the counter' => ['hash', true, [
            ['auth', 'fwd1', $bad], ['auth', 'fwd2', $bad], ['auth', 'fwd3', $bad], ['locked', 'fwd4'],
            ['locked', 'xff-other'], ['locked', 'xff1'],
        ], ['198.51.100.7']];
        yield 'trusted proxy: different forwarded IPs are counted apart' => ['hash', true, [
            ['auth', 'xff1', $bad], ['auth', 'xff2', $bad], ['auth', 'xff3', $bad], ['locked', 'xff4'],
        ], ['198.51.100.7']];
        yield 'window expiry unlocks' => ['hash', true, [
            ['auth', 'a', $bad], ['auth', 'a', $bad], ['auth', 'a', $bad], ['locked', 'a'], ['sleep', 'a'],
            ['locked', 'a'], ['auth', 'a', $ok], ['granted', 'a'],
        ]];
    }

    /**
     * @param list<array{0: string, 1: string, 2?: string}> $steps
     * @param list<string> $trusted
     */
    #[DataProvider('scenarios')]
    public function testContextPortDecidesExactlyLikeTheRequestPort(?string $hashMode, bool $enabled, array $steps, array $trusted = []): void
    {
        Request::setTrustedProxies($trusted, Request::HEADER_X_FORWARDED_FOR);
        $hash = $hashMode === null ? null : password_hash('ok-REDACTED-TEST', PASSWORD_BCRYPT, ['cost' => 4]);

        $legacy = $this->replay($hash, $enabled, $steps, static fn (PasswordMaintenanceAccessGate $gate, string $op, Request $request, string $password): mixed => match ($op) {
            'granted' => $gate->isGranted($request),
            'locked'  => $gate->isLockedOut($request),
            'auth'    => $gate->authenticate($request, $password),
            default   => $gate->revoke($request),
        });
        $context = $this->replay($hash, $enabled, $steps, static function (PasswordMaintenanceAccessGate $gate, string $op, Request $request, string $password): mixed {
            $context = MaintenanceAccessContextFactory::fromRequest($request);

            return match ($op) {
                'granted' => $gate->isGrantedFor($context),
                'locked'  => $gate->isLockedOutFor($context),
                'auth'    => $gate->authenticateFor($context, $password),
                default   => $gate->revokeFor($context),
            };
        });

        self::assertSame($legacy, $context);
    }

    public function testBothPortsShareSessionAndCacheState(): void
    {
        $cache   = new ArrayAdapter();
        $gate    = new PasswordMaintenanceAccessGate(password_hash('ok-REDACTED-TEST', PASSWORD_BCRYPT, ['cost' => 4]), true, 2, 900, $cache, new MockClock());
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/', server: ['REMOTE_ADDR' => '198.51.100.20']);
        $request->setSession($session);

        self::assertFalse($gate->authenticate($request, 'bad-REDACTED-TEST'));
        self::assertFalse($gate->authenticateFor(MaintenanceAccessContextFactory::fromRequest($request), 'bad-REDACTED-TEST'));
        self::assertTrue($gate->isLockedOut($request));
        self::assertTrue($gate->isLockedOutFor(MaintenanceAccessContextFactory::fromRequest($request)));
        self::assertTrue($cache->hasItem('nowo_maintenance_login_' . hash('sha256', '198.51.100.20')), 'Same per-IP cache key as 1.x.');

        $session->set(PasswordMaintenanceAccessGate::SESSION_KEY, true);
        self::assertTrue($gate->isGranted($request));
        self::assertTrue($gate->isGrantedFor(MaintenanceAccessContextFactory::fromRequest($request)));
        $gate->revokeFor(MaintenanceAccessContextFactory::fromRequest($request));
        self::assertFalse($gate->isGranted($request));
    }

    public function testUntrustedForwardedForCannotEvadeTheIpLockout(): void
    {
        Request::setTrustedProxies([], Request::HEADER_X_FORWARDED_FOR);
        $gate = new PasswordMaintenanceAccessGate(password_hash('ok-REDACTED-TEST', PASSWORD_BCRYPT, ['cost' => 4]), true, 3, 900, new ArrayAdapter(), new MockClock());

        for ($i = 1; $i <= 4; ++$i) {
            $request = Request::create('/', server: ['REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.' . $i]);
            $request->setSession(new Session(new MockArraySessionStorage()));
            $context = MaintenanceAccessContextFactory::fromRequest($request);
            if ($i === 4) {
                self::assertTrue($gate->isLockedOutFor($context));
                self::assertFalse($gate->authenticateFor($context, 'ok-REDACTED-TEST'));

                break;
            }
            self::assertFalse($gate->authenticateFor($context, 'bad-REDACTED-TEST'));
        }
    }

    public function testHostBuiltContextWithoutSessionIsDenied(): void
    {
        $gate = new PasswordMaintenanceAccessGate(password_hash('ok-REDACTED-TEST', PASSWORD_BCRYPT, ['cost' => 4]));

        self::assertFalse($gate->isGrantedFor(new MaintenanceAccessContext('/_maintenance', userIdentifier: 'admin', roles: ['ROLE_ADMIN'])));
    }

    /**
     * @param list<array{0: string, 1: string, 2?: string}> $steps
     * @param Closure(PasswordMaintenanceAccessGate, string, Request, string): mixed $call
     *
     * @return list<mixed>
     */
    private function replay(?string $hash, bool $enabled, array $steps, Closure $call): array
    {
        $clock    = new MockClock('2026-11-11 10:00:00');
        $cache    = new ArrayAdapter();
        $gate     = new PasswordMaintenanceAccessGate($hash, $enabled, 3, 900, $cache, $clock);
        $sessions = [];
        $trace    = [];

        foreach ($steps as $step) {
            [$op, $visitor] = $step;
            if ($op === 'sleep') {
                $clock->sleep(901);

                continue;
            }
            $server = ['REMOTE_ADDR' => $visitor === 'other-ip' ? '198.51.100.99' : '198.51.100.7'];
            if ($visitor === 'xff-other') {
                $server['HTTP_X_FORWARDED_FOR'] = '203.0.113.200';
            } elseif (str_starts_with($visitor, 'xff')) {
                // A different forwarded address per visitor: only an honoured header would split the counter.
                $server['HTTP_X_FORWARDED_FOR'] = '203.0.113.' . $visitor[3];
            } elseif (str_starts_with($visitor, 'fwd')) {
                $server['HTTP_X_FORWARDED_FOR'] = '203.0.113.50';
            }
            $request = Request::create('/_maintenance/login', 'POST', server: $server);
            if ($visitor !== 'nosession') {
                $sessions[$visitor] ??= new Session(new MockArraySessionStorage());
                $request->setSession($sessions[$visitor]);
            }
            $trace[] = [$op, $visitor, $call($gate, $op, $request, $step[2] ?? '')];
        }

        foreach ($sessions as $visitor => $session) {
            $trace[] = ['session', $visitor, $session->all()];
        }
        $trace[] = ['cache', $cache->getValues() !== []];

        return $trace;
    }
}
