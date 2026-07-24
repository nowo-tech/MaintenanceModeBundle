<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Command;

use Nowo\MaintenanceModeBundle\Command\HashPasswordCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

use function password_verify;
use function preg_match;

final class HashPasswordCommandTest extends TestCase
{
    public function testHashesPasswordWithBcryptByDefault(): void
    {
        $tester = new CommandTester(new HashPasswordCommand());
        $status = $tester->execute(['password' => 'maintenance']);

        self::assertSame(0, $status);
        self::assertTrue((bool) preg_match('/^\$2[aby]\$\d{2}\$.+$/m', $tester->getDisplay(), $m));
        self::assertTrue(password_verify('maintenance', $m[0]));
        self::assertStringContainsString('MAINTENANCE_PASSWORD_HASH', $tester->getDisplay());
    }

    public function testPromptsWhenPasswordArgumentOmitted(): void
    {
        $tester = new CommandTester(new HashPasswordCommand());
        $tester->setInputs(['prompt-secret']);
        $status = $tester->execute([]);

        self::assertSame(0, $status);
        self::assertTrue((bool) preg_match('/^\$.+$/m', $tester->getDisplay(), $m));
        self::assertTrue(password_verify('prompt-secret', $m[0]));
    }

    public function testFailsOnEmptyPasswordArgument(): void
    {
        $tester = new CommandTester(new HashPasswordCommand());
        $status = $tester->execute(['password' => '']);

        self::assertSame(1, $status);
        self::assertStringContainsString('Password must not be empty', $tester->getDisplay());
    }

    public function testFailsOnEmptyInteractivePassword(): void
    {
        $tester = new CommandTester(new HashPasswordCommand());
        $tester->setInputs(['']);
        $status = $tester->execute([]);

        self::assertSame(1, $status);
        self::assertStringContainsString('Password must not be empty', $tester->getDisplay());
    }

    public function testFailsOnInvalidAlgo(): void
    {
        $tester = new CommandTester(new HashPasswordCommand());
        $status = $tester->execute([
            'password' => 'secret',
            '--algo'   => 'md5',
        ]);

        self::assertSame(1, $status);
        self::assertStringContainsString('Invalid --algo', $tester->getDisplay());
    }

    public function testHashesWithArgon2id(): void
    {
        $tester = new CommandTester(new HashPasswordCommand());
        $status = $tester->execute([
            'password' => 'secret',
            '--algo'   => 'argon2id',
        ]);

        self::assertSame(0, $status);
        self::assertTrue((bool) preg_match('/^\$argon2id\$.+$/m', $tester->getDisplay(), $m));
        self::assertTrue(password_verify('secret', $m[0]));
    }

    public function testHashesWithDefaultAlgo(): void
    {
        $tester = new CommandTester(new HashPasswordCommand());
        $status = $tester->execute([
            'password' => 'secret',
            '--algo'   => 'default',
        ]);

        self::assertSame(0, $status);
        self::assertTrue((bool) preg_match('/^\$.+$/m', $tester->getDisplay(), $m));
        self::assertTrue(password_verify('secret', $m[0]));
    }
}
