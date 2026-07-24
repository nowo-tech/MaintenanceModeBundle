<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Service;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Event\MaintenanceDisabledEvent;
use Nowo\MaintenanceModeBundle\Event\MaintenanceEnabledEvent;
use Nowo\MaintenanceModeBundle\Event\MaintenanceUpdatedEvent;
use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

use function array_slice;

final class MaintenanceManagerTest extends TestCase
{
    private InMemoryStateStorage $stateStorage;

    private InMemoryHistoryStorage $historyStorage;

    private MaintenanceManager $manager;

    protected function setUp(): void
    {
        $this->stateStorage   = new InMemoryStateStorage();
        $this->historyStorage = new InMemoryHistoryStorage();
        $this->manager        = new MaintenanceManager(
            $this->stateStorage,
            $this->historyStorage,
            'Default maintenance message',
        );
    }

    public function testGetStateReturnsLoadedState(): void
    {
        $this->stateStorage->state = (new MaintenanceState())->withEnabled(true)->withMessage('Stored');

        self::assertTrue($this->manager->getState()->isEnabled());
        self::assertSame('Stored', $this->manager->getState()->getMessage());
    }

    public function testEnablePersistsStateAndHistory(): void
    {
        $at    = new DateTimeImmutable('2026-07-24T10:00:00+00:00');
        $state = $this->manager->enable('Deploying', 'ops', $at);

        self::assertTrue($state->isEnabled());
        self::assertSame('Deploying', $state->getMessage());
        self::assertSame('ops', $state->getUpdatedBy());
        self::assertEquals($at, $state->getActivatedAt());
        self::assertNull($state->getDeactivatedAt());
        self::assertNull($state->getScheduledEnableAt());

        self::assertTrue($this->stateStorage->state->isEnabled());
        self::assertCount(1, $this->historyStorage->entries);
        self::assertSame('enable', $this->historyStorage->entries[0]->getAction());
        self::assertSame('ops', $this->historyStorage->entries[0]->getActor());
        self::assertSame('Deploying', $this->historyStorage->entries[0]->getMessage());
    }

    public function testEnableUsesDefaultMessageWhenNull(): void
    {
        $state = $this->manager->enable(null, 'panel');

        self::assertSame('Default maintenance message', $state->getMessage());
    }

    public function testDisablePersistsStateAndHistory(): void
    {
        $this->stateStorage->state = (new MaintenanceState())
            ->withEnabled(true)
            ->withMessage('Was down')
            ->withScheduledDisableAt(new DateTimeImmutable('2026-07-25T00:00:00+00:00'));

        $at    = new DateTimeImmutable('2026-07-24T12:00:00+00:00');
        $state = $this->manager->disable('ops', $at);

        self::assertFalse($state->isEnabled());
        self::assertEquals($at, $state->getDeactivatedAt());
        self::assertNull($state->getScheduledDisableAt());
        self::assertSame('Was down', $state->getMessage());

        self::assertCount(1, $this->historyStorage->entries);
        self::assertSame('disable', $this->historyStorage->entries[0]->getAction());
    }

    public function testUpdatePersistsStateWithContextInHistory(): void
    {
        $state = (new MaintenanceState())
            ->withEnabled(true)
            ->withMessage('Scheduled')
            ->withScheduledEnableAt(new DateTimeImmutable('2026-07-25T08:00:00+00:00'));

        $updated = $this->manager->update($state, 'panel', 'schedule');

        self::assertSame('panel', $updated->getUpdatedBy());
        self::assertTrue($this->stateStorage->state->isEnabled());

        self::assertCount(1, $this->historyStorage->entries);
        self::assertSame('schedule', $this->historyStorage->entries[0]->getAction());
        self::assertSame('Scheduled', $this->historyStorage->entries[0]->getMessage());
        self::assertSame($updated->toArray(), $this->historyStorage->entries[0]->getContext());
    }

    public function testHistoryDelegatesToStorage(): void
    {
        $entry                         = new MaintenanceHistoryEntry('enable', new DateTimeImmutable(), 'a');
        $this->historyStorage->entries = [$entry];

        self::assertSame([$entry], $this->manager->history(10));
    }

    public function testIsEffectivelyEnabledDelegatesToState(): void
    {
        $this->stateStorage->state = (new MaintenanceState())->withEnabled(true);

        self::assertTrue($this->manager->isEffectivelyEnabled());
    }

    public function testScheduleSetsTimestampsAndMessage(): void
    {
        $enableAt  = new DateTimeImmutable('2026-07-25T08:00:00+00:00');
        $disableAt = new DateTimeImmutable('2026-07-25T18:00:00+00:00');

        $state = $this->manager->schedule(
            enableAt: $enableAt,
            disableAt: $disableAt,
            message: 'Window',
            updatedBy: 'ops',
        );

        self::assertEquals($enableAt, $state->getScheduledEnableAt());
        self::assertEquals($disableAt, $state->getScheduledDisableAt());
        self::assertSame('Window', $state->getMessage());
        self::assertSame('schedule', $this->historyStorage->entries[0]->getAction());
    }

    public function testScheduleClearMissingClearsUnsetTimestamps(): void
    {
        $this->stateStorage->state = (new MaintenanceState())
            ->withScheduledEnableAt(new DateTimeImmutable('2026-07-25T08:00:00+00:00'))
            ->withScheduledDisableAt(new DateTimeImmutable('2026-07-25T18:00:00+00:00'));

        $state = $this->manager->schedule(
            updatedBy: 'ops',
            clearMissing: true,
        );

        self::assertNull($state->getScheduledEnableAt());
        self::assertNull($state->getScheduledDisableAt());
    }

    public function testScheduleWithoutClearMissingKeepsExistingWhenNull(): void
    {
        $enableAt                  = new DateTimeImmutable('2026-07-25T08:00:00+00:00');
        $this->stateStorage->state = (new MaintenanceState())->withScheduledEnableAt($enableAt);

        $state = $this->manager->schedule(disableAt: new DateTimeImmutable('2026-07-25T18:00:00+00:00'));

        self::assertEquals($enableAt, $state->getScheduledEnableAt());
        self::assertNotNull($state->getScheduledDisableAt());
    }

    public function testClearScheduleRemovesBothTimestamps(): void
    {
        $this->stateStorage->state = (new MaintenanceState())
            ->withScheduledEnableAt(new DateTimeImmutable('2026-07-25T08:00:00+00:00'))
            ->withScheduledDisableAt(new DateTimeImmutable('2026-07-25T18:00:00+00:00'));

        $state = $this->manager->clearSchedule('ops');

        self::assertNull($state->getScheduledEnableAt());
        self::assertNull($state->getScheduledDisableAt());
        self::assertSame('clear_schedule', $this->historyStorage->entries[0]->getAction());
    }

    public function testDispatchesDomainEventsWhenDispatcherPresent(): void
    {
        $dispatcher = new class implements EventDispatcherInterface {
            /** @var list<object> */
            public array $events = [];

            public function dispatch(object $event): object
            {
                $this->events[] = $event;

                return $event;
            }
        };

        $manager = new MaintenanceManager(
            $this->stateStorage,
            $this->historyStorage,
            'Default',
            $dispatcher,
        );

        $manager->enable('On', 'ops');
        $manager->schedule(disableAt: new DateTimeImmutable('+1 hour'), updatedBy: 'ops');
        $manager->disable('ops');

        self::assertInstanceOf(MaintenanceEnabledEvent::class, $dispatcher->events[0]);
        self::assertInstanceOf(MaintenanceUpdatedEvent::class, $dispatcher->events[1]);
        self::assertSame('schedule', $dispatcher->events[1]->action);
        self::assertInstanceOf(MaintenanceDisabledEvent::class, $dispatcher->events[2]);
    }
}

/** @internal */
final class InMemoryStateStorage implements MaintenanceStateStorageInterface
{
    public MaintenanceState $state;

    public function __construct()
    {
        $this->state = new MaintenanceState();
    }

    public function load(): MaintenanceState
    {
        return $this->state;
    }

    public function save(MaintenanceState $state): void
    {
        $this->state = $state;
    }
}

/** @internal */
final class InMemoryHistoryStorage implements MaintenanceHistoryStorageInterface
{
    /** @var list<MaintenanceHistoryEntry> */
    public array $entries = [];

    public function append(MaintenanceHistoryEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    public function list(int $limit = 50): array
    {
        return array_slice($this->entries, 0, $limit);
    }
}
