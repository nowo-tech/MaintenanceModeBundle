<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Service;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Event\MaintenanceDisabledEvent;
use Nowo\MaintenanceModeBundle\Event\MaintenanceEnabledEvent;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

use function class_implements;

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
        self::assertNull($state->getScheduledEnableAt());
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
