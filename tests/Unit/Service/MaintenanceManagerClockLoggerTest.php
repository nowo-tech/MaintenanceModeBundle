<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Service;

use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Service\MaintenanceManager;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\Clock\MockClock;

/**
 * REQ-DI-001 / REQ-OBS-001: the manager takes its time from the injected clock and logs
 * state changes with a structured context that never contains the actor name.
 */
final class MaintenanceManagerClockLoggerTest extends TestCase
{
    public function testUsesInjectedClockAndLogsWithoutActorName(): void
    {
        $stateStorage = new class implements MaintenanceStateStorageInterface {
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
        };
        $historyStorage = new class implements MaintenanceHistoryStorageInterface {
            /** @var list<MaintenanceHistoryEntry> */
            public array $entries = [];

            public function append(MaintenanceHistoryEntry $entry): void
            {
                $this->entries[] = $entry;
            }

            public function list(int $limit = 50): array
            {
                return $this->entries;
            }
        };
        $logger = new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array<mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
        $clock   = new MockClock('2030-01-02 03:04:05');
        $manager = new MaintenanceManager($stateStorage, $historyStorage, null, null, $clock, $logger);

        $state = $manager->enable('Upgrading', 'alice');
        self::assertSame('2030-01-02 03:04:05', $state->getActivatedAt()?->format('Y-m-d H:i:s'));
        self::assertTrue($manager->isEffectivelyEnabled());

        $clock->sleep(60);
        $state = $manager->disable();
        self::assertSame('2030-01-02 03:05:05', $state->getDeactivatedAt()?->format('Y-m-d H:i:s'));

        $manager->clearSchedule('alice');
        self::assertSame('2030-01-02 03:05:05', $historyStorage->entries[2]->getOccurredAt()->format('Y-m-d H:i:s'));

        self::assertSame(
            [
                ['level' => 'info', 'message' => 'Maintenance mode enabled.', 'context' => ['bundle' => 'maintenance_mode', 'action' => 'enable', 'by_user' => true]],
                ['level' => 'info', 'message' => 'Maintenance mode disabled.', 'context' => ['bundle' => 'maintenance_mode', 'action' => 'disable', 'by_user' => false]],
                ['level' => 'info', 'message' => 'Maintenance mode state updated.', 'context' => ['bundle' => 'maintenance_mode', 'action' => 'clear_schedule', 'by_user' => true]],
            ],
            $logger->records,
        );
        self::assertStringNotContainsString('alice', serialize($logger->records));
    }
}
