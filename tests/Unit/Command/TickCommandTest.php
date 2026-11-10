<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Command;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Command\TickCommand;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManagerInterface;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryHistoryStorage;
use Nowo\MaintenanceModeBundle\Tests\Unit\Service\InMemoryStateStorage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class TickCommandTest extends TestCase
{
    private InMemoryStateStorage $stateStorage;

    private InMemoryHistoryStorage $historyStorage;

    private MaintenanceManager $manager;

    protected function setUp(): void
    {
        $this->stateStorage   = new InMemoryStateStorage();
        $this->historyStorage = new InMemoryHistoryStorage();
        $this->manager        = new MaintenanceManager($this->stateStorage, $this->historyStorage);
    }

    public function testNothingDueIsSilentUnlessVerbose(): void
    {
        $tester = new CommandTester(new TickCommand($this->manager));

        self::assertSame(0, $tester->execute([]));
        self::assertSame('', $tester->getDisplay());

        self::assertSame(0, $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]));
        self::assertStringContainsString('No scheduled transition due.', $tester->getDisplay());
    }

    public function testWindowStartEnablesWithScheduleActor(): void
    {
        $this->stateStorage->state = (new MaintenanceState())->withScheduledEnableAt(new DateTimeImmutable('-5 minutes'));

        $tester = new CommandTester(new TickCommand($this->manager));

        self::assertSame(0, $tester->execute([]));
        self::assertStringContainsString('ENABLED', $tester->getDisplay());
        self::assertTrue($this->stateStorage->state->isEnabled());
        self::assertSame(MaintenanceManagerInterface::ACTOR_SCHEDULE, $this->stateStorage->state->getUpdatedBy());
    }

    public function testWindowEndDisablesWithGivenActor(): void
    {
        $this->stateStorage->state = (new MaintenanceState())->withEnabled(true)->withScheduledDisableAt(new DateTimeImmutable('-5 minutes'));

        $tester = new CommandTester(new TickCommand($this->manager));

        self::assertSame(0, $tester->execute(['--actor' => 'cron']));
        self::assertStringContainsString('DISABLED', $tester->getDisplay());
        self::assertFalse($this->stateStorage->state->isEnabled());
        self::assertSame('cron', $this->historyStorage->entries[0]->getActor());
    }

    public function testEmptyActorFallsBackToSchedule(): void
    {
        $this->stateStorage->state = (new MaintenanceState())->withScheduledEnableAt(new DateTimeImmutable('-5 minutes'));

        (new CommandTester(new TickCommand($this->manager)))->execute(['--actor' => '']);

        self::assertSame('schedule', $this->stateStorage->state->getUpdatedBy());
    }
}
