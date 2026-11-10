<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Service;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Event\MaintenanceDisabledEvent;
use Nowo\MaintenanceModeBundle\Event\MaintenanceEnabledEvent;
use Nowo\MaintenanceModeBundle\Event\MaintenanceUpdatedEvent;
use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;

/**
 * Application API of the bundle: read, toggle and schedule maintenance mode.
 *
 * Type-hint this interface (autowired to {@see MaintenanceManager}) in host code and double it in tests. Every write
 * appends a history entry and dispatches {@see MaintenanceEnabledEvent},
 * {@see MaintenanceDisabledEvent} or
 * {@see MaintenanceUpdatedEvent}.
 */
interface MaintenanceManagerInterface
{
    /**
     * Actor recorded when {@see applySchedule()} materialises a scheduled window.
     */
    public const ACTOR_SCHEDULE = 'schedule';

    public function getState(): MaintenanceState;

    public function isEffectivelyEnabled(?DateTimeImmutable $now = null): bool;

    public function enable(?string $message = null, ?string $updatedBy = null, ?DateTimeImmutable $at = null): MaintenanceState;

    public function disable(?string $updatedBy = null, ?DateTimeImmutable $at = null): MaintenanceState;

    public function schedule(
        ?DateTimeImmutable $enableAt = null,
        ?DateTimeImmutable $disableAt = null,
        ?string $message = null,
        ?string $updatedBy = null,
        bool $clearMissing = false,
    ): MaintenanceState;

    public function clearSchedule(?string $updatedBy = null): MaintenanceState;

    public function update(MaintenanceState $state, ?string $updatedBy = null, string $action = 'update'): MaintenanceState;

    /**
     * Materialises a scheduled window that started or ended: when the stored `enabled` flag differs from
     * {@see MaintenanceState::isEffectivelyEnabled()} at `$now`, the state is written as enabled / disabled (history
     * entry + MaintenanceEnabledEvent / MaintenanceDisabledEvent with the given actor). The effective state never
     * changes: only the stored flag catches up with the schedule. Returns the new state, or null when nothing was due.
     *
     * Run it periodically (`nowo:maintenance-mode:tick` from cron or Symfony Scheduler); without it, scheduled windows
     * keep working at read time but dispatch no event.
     */
    public function applySchedule(?DateTimeImmutable $now = null, ?string $actor = self::ACTOR_SCHEDULE): ?MaintenanceState;

    /**
     * @return list<MaintenanceHistoryEntry>
     */
    public function history(int $limit = 50): array;
}
