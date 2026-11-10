<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Service;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Event\MaintenanceDisabledEvent;
use Nowo\MaintenanceModeBundle\Event\MaintenanceEnabledEvent;
use Nowo\MaintenanceModeBundle\Event\MaintenanceUpdatedEvent;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManagerInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Clock\MockClock;

use function class_implements;
use function count;

/**
 * applySchedule() (nowo:maintenance-mode:tick): scheduled windows dispatch the enabled / disabled events and the
 * effective state never changes.
 */
final class MaintenanceManagerApplyScheduleTest extends TestCase
{
    private InMemoryStateStorage $stateStorage;

    private InMemoryHistoryStorage $historyStorage;

    private RecordingEventDispatcher $dispatcher;

    private MaintenanceManager $manager;

    protected function setUp(): void
    {
        $this->stateStorage   = new InMemoryStateStorage();
        $this->historyStorage = new InMemoryHistoryStorage();
        $this->dispatcher     = new RecordingEventDispatcher();
        $this->manager        = new MaintenanceManager($this->stateStorage, $this->historyStorage, 'Default', $this->dispatcher);
    }

    public function testImplementsTheInterface(): void
    {
        self::assertContains(MaintenanceManagerInterface::class, class_implements(MaintenanceManager::class));
    }

    public function testNothingDueReturnsNullAndWritesNothing(): void
    {
        $this->stateStorage->state = (new MaintenanceState())
            ->withScheduledEnableAt(new DateTimeImmutable('2026-11-10T12:00:00+00:00'));

        self::assertNull($this->manager->applySchedule(new DateTimeImmutable('2026-11-10T11:00:00+00:00')));
        self::assertSame([], $this->historyStorage->entries);
        self::assertSame([], $this->dispatcher->events);
        self::assertFalse($this->stateStorage->state->isEnabled());
    }

    public function testWindowStartEnablesAndDispatchesEnabledEvent(): void
    {
        $start                     = new DateTimeImmutable('2026-11-10T10:00:00+00:00');
        $end                       = new DateTimeImmutable('2026-11-10T12:00:00+00:00');
        $this->stateStorage->state = (new MaintenanceState())
            ->withScheduledEnableAt($start)
            ->withScheduledDisableAt($end)
            ->withMessage('Planned upgrade');

        $state = $this->manager->applySchedule(new DateTimeImmutable('2026-11-10T10:05:00+00:00'));

        self::assertInstanceOf(MaintenanceState::class, $state);
        self::assertTrue($state->isEnabled());
        self::assertEquals($start, $state->getScheduledEnableAt());
        self::assertEquals($end, $state->getScheduledDisableAt());
        self::assertEquals($start, $state->getActivatedAt());
        self::assertSame('Planned upgrade', $state->getMessage());
        self::assertSame(MaintenanceManagerInterface::ACTOR_SCHEDULE, $state->getUpdatedBy());

        self::assertCount(1, $this->dispatcher->events);
        $event = $this->dispatcher->events[0];
        self::assertInstanceOf(MaintenanceEnabledEvent::class, $event);
        self::assertSame('schedule', $event->actor);

        self::assertCount(1, $this->historyStorage->entries);
        $entry = $this->historyStorage->entries[0];
        self::assertSame('enable', $entry->getAction());
        self::assertSame('schedule', $entry->getActor());
        self::assertSame(['trigger' => 'schedule'], $entry->getContext());
        self::assertEquals($start, $entry->getOccurredAt());

        // Idempotent: the next tick inside the window has nothing to do.
        self::assertNull($this->manager->applySchedule(new DateTimeImmutable('2026-11-10T10:06:00+00:00')));
    }

    public function testWindowEndDisablesClearsScheduleAndDispatchesDisabledEvent(): void
    {
        $end                       = new DateTimeImmutable('2026-11-10T12:00:00+00:00');
        $this->stateStorage->state = (new MaintenanceState())
            ->withEnabled(true)
            ->withScheduledEnableAt(new DateTimeImmutable('2026-11-10T13:00:00+00:00'))
            ->withScheduledDisableAt($end);

        $state = $this->manager->applySchedule(new DateTimeImmutable('2026-11-10T12:01:00+00:00'), 'cron');

        self::assertInstanceOf(MaintenanceState::class, $state);
        self::assertFalse($state->isEnabled());
        self::assertNull($state->getScheduledEnableAt());
        self::assertNull($state->getScheduledDisableAt());
        self::assertEquals($end, $state->getDeactivatedAt());
        self::assertSame('cron', $state->getUpdatedBy());

        $event = $this->dispatcher->events[0];
        self::assertInstanceOf(MaintenanceDisabledEvent::class, $event);
        self::assertSame('cron', $event->actor);
        self::assertSame('disable', $this->historyStorage->entries[0]->getAction());
    }

    public function testEnableOnlyScheduleWithoutMessageUsesDefaultMessage(): void
    {
        $this->stateStorage->state = (new MaintenanceState())
            ->withScheduledEnableAt(new DateTimeImmutable('2026-11-10T10:00:00+00:00'));

        $state = $this->manager->applySchedule(new DateTimeImmutable('2026-11-10T10:00:00+00:00'));

        self::assertInstanceOf(MaintenanceState::class, $state);
        self::assertTrue($state->isEnabled());
        self::assertSame('Default', $state->getMessage());
    }

    public function testUsesTheClockWhenNoTimeIsGiven(): void
    {
        $this->stateStorage->state = (new MaintenanceState())
            ->withScheduledEnableAt(new DateTimeImmutable('-1 minute'));

        $state = $this->manager->applySchedule();

        self::assertInstanceOf(MaintenanceState::class, $state);
        self::assertTrue($state->isEnabled());
    }

    public function testAWriteBetweenLoadAndSaveWinsOverTheTick(): void
    {
        $storage = new class implements MaintenanceStateStorageInterface {
            public int $loads = 0;

            public ?MaintenanceState $saved = null;

            public function load(): MaintenanceState
            {
                ++$this->loads;
                $state = (new MaintenanceState())->withScheduledEnableAt(new DateTimeImmutable('2026-11-10T10:00:00+00:00'));

                // The second load sees an operator's edit (the window moved).
                return $this->loads === 1 ? $state : $state->withScheduledEnableAt(new DateTimeImmutable('2026-11-11T10:00:00+00:00'));
            }

            public function save(MaintenanceState $state): void
            {
                $this->saved = $state;
            }
        };
        $manager = new MaintenanceManager($storage, $this->historyStorage, null, $this->dispatcher);

        self::assertNull($manager->applySchedule(new DateTimeImmutable('2026-11-10T10:05:00+00:00')));
        self::assertNull($storage->saved);
        self::assertSame([], $this->dispatcher->events);
    }

    public function testClearingAWindowTheTickStartedEndsMaintenance(): void
    {
        $manager = $this->tickedWindow('2026-11-10T10:30:00+00:00');

        $state = $manager->clearSchedule('ops');

        self::assertFalse($state->isEnabled());
        self::assertFalse($state->isEffectivelyEnabled(new DateTimeImmutable('2026-11-10T10:30:00+00:00')));
        self::assertEquals(new DateTimeImmutable('2026-11-10T10:30:00+00:00'), $state->getDeactivatedAt());
        $last = $this->dispatcher->events[count($this->dispatcher->events) - 1];
        self::assertInstanceOf(MaintenanceDisabledEvent::class, $last);
        self::assertSame('ops', $last->actor);
        // The next tick has nothing to announce.
        self::assertNull($manager->applySchedule(new DateTimeImmutable('2026-11-10T10:31:00+00:00')));
    }

    public function testMovingAWindowTheTickStartedEndsItNowAndRunsItLater(): void
    {
        $manager = $this->tickedWindow('2026-11-10T10:30:00+00:00');

        $state = $manager->schedule(
            new DateTimeImmutable('2026-11-11T10:00:00+00:00'),
            new DateTimeImmutable('2026-11-11T12:00:00+00:00'),
            updatedBy: 'ops',
            clearMissing: true,
        );

        self::assertFalse($state->isEffectivelyEnabled(new DateTimeImmutable('2026-11-10T10:30:00+00:00')));
        self::assertFalse($state->isEffectivelyEnabled(new DateTimeImmutable('2026-11-11T09:59:00+00:00')));
        self::assertTrue($state->isEffectivelyEnabled(new DateTimeImmutable('2026-11-11T10:00:00+00:00')));
        self::assertFalse($state->isEffectivelyEnabled(new DateTimeImmutable('2026-11-11T12:00:00+00:00')));
        self::assertInstanceOf(MaintenanceDisabledEvent::class, $this->dispatcher->events[count($this->dispatcher->events) - 1]);
    }

    public function testExtendingAWindowTheTickStartedKeepsItOnWithoutADisabledEvent(): void
    {
        $manager = $this->tickedWindow('2026-11-10T10:30:00+00:00');
        $count   = count($this->dispatcher->events);

        // The panel form is pre-filled with the stored window (the tick keeps the start).
        $state = $manager->schedule(
            new DateTimeImmutable('2026-11-10T10:00:00+00:00'),
            new DateTimeImmutable('2026-11-10T13:00:00+00:00'),
            updatedBy: 'ops',
            clearMissing: true,
        );

        self::assertTrue($state->isEnabled());
        self::assertTrue($state->isEffectivelyEnabled(new DateTimeImmutable('2026-11-10T12:30:00+00:00')));
        self::assertFalse($state->isEffectivelyEnabled(new DateTimeImmutable('2026-11-10T13:00:00+00:00')));
        self::assertCount($count + 1, $this->dispatcher->events);
        self::assertInstanceOf(MaintenanceUpdatedEvent::class, $this->dispatcher->events[$count]);
        self::assertNull($manager->applySchedule(new DateTimeImmutable('2026-11-10T12:00:00+00:00')));
    }

    public function testClearingTheScheduleKeepsAManualMaintenanceOn(): void
    {
        $clock   = new MockClock('2026-11-10T10:30:00+00:00');
        $manager = new MaintenanceManager($this->stateStorage, $this->historyStorage, 'Default', $this->dispatcher, $clock);
        $manager->enable('Manual', 'ops');
        $manager->schedule(disableAt: new DateTimeImmutable('2026-11-10T12:00:00+00:00'), updatedBy: 'ops');

        $state = $manager->clearSchedule('ops');

        self::assertTrue($state->isEnabled());
        self::assertTrue($state->isEffectivelyEnabled(new DateTimeImmutable('2026-11-11T00:00:00+00:00')));
    }

    public function testDisableDuringAnUntickedWindowLeavesMaintenanceOff(): void
    {
        $this->stateStorage->state = (new MaintenanceState())
            ->withScheduledEnableAt(new DateTimeImmutable('2026-11-10T10:00:00+00:00'))
            ->withScheduledDisableAt(new DateTimeImmutable('2026-11-10T12:00:00+00:00'));

        $state = $this->manager->disable('ops', new DateTimeImmutable('2026-11-10T10:30:00+00:00'));

        self::assertNull($state->getScheduledEnableAt());
        self::assertNull($state->getScheduledDisableAt());
        foreach (['2026-11-10T10:30:00+00:00', '2026-11-10T11:59:00+00:00', '2026-11-11T00:00:00+00:00'] as $at) {
            self::assertFalse($state->isEffectivelyEnabled(new DateTimeImmutable($at)), $at);
        }
    }

    public function testDisableKeepsAFutureStart(): void
    {
        $start                     = new DateTimeImmutable('2026-11-11T10:00:00+00:00');
        $this->stateStorage->state = (new MaintenanceState())->withEnabled(true)->withScheduledEnableAt($start);

        $state = $this->manager->disable('ops', new DateTimeImmutable('2026-11-10T10:30:00+00:00'));

        self::assertEquals($start, $state->getScheduledEnableAt());
    }

    public function testTheTickMarksTheStateAndManualWritesClearTheMarker(): void
    {
        $manager = $this->tickedWindow('2026-11-10T10:30:00+00:00');
        self::assertTrue($this->stateStorage->state->isStartedBySchedule());
        self::assertTrue($this->stateStorage->state->toArray()['started_by_schedule']);

        self::assertFalse($manager->enable('Manual', 'ops')->isStartedBySchedule());

        $manager = $this->tickedWindow('2026-11-10T10:30:00+00:00');
        self::assertFalse($manager->disable('ops')->isStartedBySchedule());
    }

    public function testTheTickAtTheWindowEndClearsTheMarker(): void
    {
        $this->tickedWindow('2026-11-10T10:30:00+00:00');

        $state = $this->manager->applySchedule(new DateTimeImmutable('2026-11-10T12:00:00+00:00'));

        self::assertInstanceOf(MaintenanceState::class, $state);
        self::assertFalse($state->isEnabled());
        self::assertFalse($state->isStartedBySchedule());
    }

    /**
     * KH-M-L5: enable(at: $start) followed by schedule(enableAt: $start) in the same second is still a manual enable.
     */
    public function testAManualEnableAtTheSameSecondAsALaterStartIsNotEndedByClear(): void
    {
        $start   = new DateTimeImmutable('2026-11-10T10:00:00+00:00');
        $manager = new MaintenanceManager($this->stateStorage, $this->historyStorage, 'Default', $this->dispatcher, new MockClock('2026-11-10T10:30:00+00:00'));
        $manager->enable('Manual', 'ops', $start);
        $manager->schedule($start, new DateTimeImmutable('2026-11-10T12:00:00+00:00'), updatedBy: 'ops');
        self::assertEquals($start, $this->stateStorage->state->getActivatedAt());
        $count = count($this->dispatcher->events);

        $state = $manager->clearSchedule('ops');

        self::assertTrue($state->isEnabled());
        self::assertTrue($state->isEffectivelyEnabled(new DateTimeImmutable('2026-11-11T00:00:00+00:00')));
        self::assertCount($count + 1, $this->dispatcher->events);
        self::assertInstanceOf(MaintenanceUpdatedEvent::class, $this->dispatcher->events[$count]);
    }

    public function testAManualEnableAtTheSameSecondAsALaterStartIsNotEndedByMovingIt(): void
    {
        $start   = new DateTimeImmutable('2026-11-10T10:00:00+00:00');
        $manager = new MaintenanceManager($this->stateStorage, $this->historyStorage, 'Default', $this->dispatcher, new MockClock('2026-11-10T10:30:00+00:00'));
        $manager->enable('Manual', 'ops', $start);
        $manager->schedule($start, updatedBy: 'ops');

        $state = $manager->schedule(new DateTimeImmutable('2026-11-11T10:00:00+00:00'), updatedBy: 'ops', clearMissing: true);

        self::assertTrue($state->isEnabled());
        self::assertTrue($state->isEffectivelyEnabled(new DateTimeImmutable('2026-11-10T10:30:00+00:00')));
        self::assertNotInstanceOf(MaintenanceDisabledEvent::class, $this->dispatcher->events[count($this->dispatcher->events) - 1]);
    }

    public function testAManualEnableInsideATickedWindowSurvivesClear(): void
    {
        $manager = $this->tickedWindow('2026-11-10T10:30:00+00:00');
        $manager->enable('Manual', 'ops', new DateTimeImmutable('2026-11-10T10:00:00+00:00'));
        $manager->schedule(new DateTimeImmutable('2026-11-10T10:00:00+00:00'), new DateTimeImmutable('2026-11-10T12:00:00+00:00'), updatedBy: 'ops');

        self::assertTrue($manager->clearSchedule('ops')->isEnabled());
    }

    /**
     * KH-M-L6: moving the start of a running window to another past time keeps it owned; Clear then ends it.
     */
    public function testMovingTheStartOfATickedWindowWithinThePastKeepsItOwned(): void
    {
        $manager = $this->tickedWindow('2026-11-10T10:30:00+00:00');

        $state = $manager->schedule(
            new DateTimeImmutable('2026-11-10T10:15:00+00:00'),
            new DateTimeImmutable('2026-11-10T12:00:00+00:00'),
            updatedBy: 'ops',
            clearMissing: true,
        );

        self::assertTrue($state->isEnabled());
        self::assertTrue($state->isStartedBySchedule());
        // activatedAt stays the instant maintenance really went on.
        self::assertEquals(new DateTimeImmutable('2026-11-10T10:00:00+00:00'), $state->getActivatedAt());

        $state = $manager->clearSchedule('ops');

        self::assertFalse($state->isEnabled());
        self::assertFalse($state->isEffectivelyEnabled(new DateTimeImmutable('2026-11-11T00:00:00+00:00')));
        self::assertInstanceOf(MaintenanceDisabledEvent::class, $this->dispatcher->events[count($this->dispatcher->events) - 1]);
    }

    /**
     * KH-M-L6: a start with seconds (API / CLI) re-saved through the minute-precision panel form stays owned.
     */
    public function testReSavingAStartWithSecondsAtMinutePrecisionKeepsItOwned(): void
    {
        $this->stateStorage->state = (new MaintenanceState())
            ->withScheduledEnableAt(new DateTimeImmutable('2026-11-10T10:00:30+00:00'))
            ->withScheduledDisableAt(new DateTimeImmutable('2026-11-10T12:00:00+00:00'));
        $manager = new MaintenanceManager($this->stateStorage, $this->historyStorage, 'Default', $this->dispatcher, new MockClock('2026-11-10T10:30:00+00:00'));
        self::assertInstanceOf(MaintenanceState::class, $manager->applySchedule(new DateTimeImmutable('2026-11-10T10:01:00+00:00')));

        $state = $manager->schedule(
            new DateTimeImmutable('2026-11-10T10:00:00+00:00'),
            new DateTimeImmutable('2026-11-10T12:00:00+00:00'),
            updatedBy: 'ops',
            clearMissing: true,
        );
        self::assertTrue($state->isStartedBySchedule());

        $state = $manager->clearSchedule('ops');

        self::assertFalse($state->isEnabled());
        self::assertFalse($state->isEffectivelyEnabled(new DateTimeImmutable('2026-11-11T00:00:00+00:00')));
    }

    /**
     * A window a 1.16.0 tick started has no marker and reads as manual (fail-closed): Clear leaves it on, Disable ends it.
     */
    public function testA1160StateWithoutMarkerFailsClosed(): void
    {
        $this->stateStorage->state = MaintenanceState::fromArray([
            'enabled'              => true,
            'activated_at'         => '2026-11-10T10:00:00+00:00',
            'scheduled_enable_at'  => '2026-11-10T10:00:00+00:00',
            'scheduled_disable_at' => '2026-11-10T12:00:00+00:00',
            'updated_by'           => 'schedule',
        ]);
        $manager = new MaintenanceManager($this->stateStorage, $this->historyStorage, 'Default', $this->dispatcher, new MockClock('2026-11-10T10:30:00+00:00'));

        self::assertTrue($manager->clearSchedule('ops')->isEnabled());
        self::assertFalse($manager->disable('ops')->isEffectivelyEnabled(new DateTimeImmutable('2026-11-10T10:30:00+00:00')));
    }

    /**
     * A 10:00-12:00 window started by the tick at 10:00; the manager's clock is at $now.
     */
    private function tickedWindow(string $now): MaintenanceManager
    {
        $this->stateStorage->state = (new MaintenanceState())
            ->withScheduledEnableAt(new DateTimeImmutable('2026-11-10T10:00:00+00:00'))
            ->withScheduledDisableAt(new DateTimeImmutable('2026-11-10T12:00:00+00:00'));
        $manager = new MaintenanceManager($this->stateStorage, $this->historyStorage, 'Default', $this->dispatcher, new MockClock($now));
        self::assertInstanceOf(MaintenanceState::class, $manager->applySchedule(new DateTimeImmutable('2026-11-10T10:00:00+00:00')));
        self::assertInstanceOf(MaintenanceEnabledEvent::class, $this->dispatcher->events[0]);

        return $manager;
    }

    /**
     * The effective state before and after materialising is the same at every later instant.
     */
    #[DataProvider('provideSchedules')]
    public function testMaterialisingNeverChangesTheEffectiveState(MaintenanceState $initial, string $tick): void
    {
        $this->stateStorage->state = $initial;
        $this->manager->applySchedule(new DateTimeImmutable($tick));
        $after = $this->stateStorage->state;

        foreach (['2026-11-10T09:00:00+00:00', '2026-11-10T10:30:00+00:00', '2026-11-10T11:59:59+00:00', '2026-11-10T12:00:00+00:00', '2026-11-10T13:30:00+00:00', '2026-11-11T00:00:00+00:00'] as $at) {
            $now = new DateTimeImmutable($at);
            if ($now < new DateTimeImmutable($tick)) {
                continue;
            }
            self::assertSame($initial->isEffectivelyEnabled($now), $after->isEffectivelyEnabled($now), $at);
        }
    }

    /**
     * @return iterable<string, array{MaintenanceState, string}>
     */
    public static function provideSchedules(): iterable
    {
        $ten    = new DateTimeImmutable('2026-11-10T10:00:00+00:00');
        $twelve = new DateTimeImmutable('2026-11-10T12:00:00+00:00');
        $one    = new DateTimeImmutable('2026-11-10T13:00:00+00:00');

        yield 'window start' => [(new MaintenanceState())->withScheduledEnableAt($ten)->withScheduledDisableAt($twelve), '2026-11-10T10:30:00+00:00'];
        yield 'enable only' => [(new MaintenanceState())->withScheduledEnableAt($ten), '2026-11-10T10:30:00+00:00'];
        yield 'window end' => [(new MaintenanceState())->withEnabled(true)->withScheduledDisableAt($twelve), '2026-11-10T12:00:00+00:00'];
        yield 'window end, stale past start' => [(new MaintenanceState())->withEnabled(true)->withScheduledEnableAt($ten)->withScheduledDisableAt($ten), '2026-11-10T12:00:00+00:00'];
        yield 'window end, later start' => [(new MaintenanceState())->withEnabled(true)->withScheduledEnableAt($one)->withScheduledDisableAt($twelve), '2026-11-10T12:00:00+00:00'];
    }
}

/** @internal */
final class RecordingEventDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
