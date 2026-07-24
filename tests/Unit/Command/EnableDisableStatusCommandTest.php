<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Command;

use Nowo\MaintenanceModeBundle\Command\DisableCommand;
use Nowo\MaintenanceModeBundle\Command\EnableCommand;
use Nowo\MaintenanceModeBundle\Command\StatusCommand;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryHistoryStorage;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryStateStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class EnableDisableStatusCommandTest extends TestCase
{
    private InMemoryStateStorage $stateStorage;

    private MaintenanceManager $manager;

    protected function setUp(): void
    {
        $this->stateStorage = new InMemoryStateStorage();
        $this->manager      = new MaintenanceManager(
            $this->stateStorage,
            new InMemoryHistoryStorage(),
            'CLI default',
        );
    }

    public function testEnableSucceeds(): void
    {
        $tester = new CommandTester(new EnableCommand($this->manager));
        $status = $tester->execute([
            '--message' => 'Deploy',
            '--actor'   => 'ci',
        ]);

        self::assertSame(0, $status);
        self::assertStringContainsString('ENABLED', $tester->getDisplay());
        self::assertTrue($this->stateStorage->state->isEnabled());
        self::assertSame('Deploy', $this->stateStorage->state->getMessage());
        self::assertSame('ci', $this->stateStorage->state->getUpdatedBy());
    }

    public function testEnableWithUntilSchedulesDisable(): void
    {
        $tester = new CommandTester(new EnableCommand($this->manager));
        $status = $tester->execute([
            '--until' => '2030-01-01T00:00:00+00:00',
        ]);

        self::assertSame(0, $status);
        self::assertNotNull($this->stateStorage->state->getScheduledDisableAt());
    }

    public function testEnableFailsOnInvalidUntil(): void
    {
        $tester = new CommandTester(new EnableCommand($this->manager));
        $status = $tester->execute(['--until' => 'not-a-date']);

        self::assertSame(1, $status);
        self::assertStringContainsString('Invalid --until', $tester->getDisplay());
    }

    public function testDisableSucceeds(): void
    {
        $this->stateStorage->state = (new MaintenanceState())->withEnabled(true);
        $tester                    = new CommandTester(new DisableCommand($this->manager));
        $status                    = $tester->execute([]);

        self::assertSame(0, $status);
        self::assertStringContainsString('DISABLED', $tester->getDisplay());
        self::assertFalse($this->stateStorage->state->isEnabled());
    }

    public function testStatusExitZeroWhenOff(): void
    {
        $tester = new CommandTester(new StatusCommand($this->manager));
        $status = $tester->execute([]);

        self::assertSame(0, $status);
        self::assertStringContainsString('Effectively on', $tester->getDisplay());
    }

    public function testStatusExitTwoWhenEffectivelyOn(): void
    {
        $this->stateStorage->state = (new MaintenanceState())->withEnabled(true);
        $tester                    = new CommandTester(new StatusCommand($this->manager));
        $status                    = $tester->execute([], ['verbosity' => 64]);

        self::assertSame(2, $status);
        self::assertStringContainsString('"enabled": true', $tester->getDisplay());
    }
}
