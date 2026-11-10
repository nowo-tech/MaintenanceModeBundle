<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Service;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Event\MaintenanceDisabledEvent;
use Nowo\MaintenanceModeBundle\Event\MaintenanceEnabledEvent;
use Nowo\MaintenanceModeBundle\Event\MaintenanceUpdatedEvent;
use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\NativeClock;

/**
 * Application service for enabling / disabling / scheduling maintenance (default {@see MaintenanceManagerInterface}).
 */
final class MaintenanceManager implements MaintenanceManagerInterface
{
    private readonly ClockInterface $clock;

    private readonly LoggerInterface $logger;

    /**
     * @param MaintenanceStateStorageInterface $stateStorage Persists the current state
     * @param MaintenanceHistoryStorageInterface $historyStorage Appends toggle/schedule history
     * @param string|null $defaultMessage Message used when enable() gets none
     * @param EventDispatcherInterface|null $eventDispatcher Dispatches enabled/disabled/updated events
     * @param ClockInterface|null $clock Time source (REQ-DI-001); system clock when null
     * @param LoggerInterface|null $logger Logs state changes without actor names (REQ-OBS-001)
     */
    public function __construct(
        private readonly MaintenanceStateStorageInterface $stateStorage,
        private readonly MaintenanceHistoryStorageInterface $historyStorage,
        private readonly ?string $defaultMessage = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
        ?ClockInterface $clock = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->clock  = $clock ?? new NativeClock();
        $this->logger = $logger ?? new NullLogger();
    }

    public function getState(): MaintenanceState
    {
        return $this->stateStorage->load();
    }

    public function isEffectivelyEnabled(?DateTimeImmutable $now = null): bool
    {
        return $this->getState()->isEffectivelyEnabled($now ?? $this->clock->now());
    }

    public function enable(?string $message = null, ?string $updatedBy = null, ?DateTimeImmutable $at = null): MaintenanceState
    {
        return $this->writeEnabled($this->stateStorage->load(), $message ?? $this->defaultMessage, $updatedBy, $at ?? $this->clock->now());
    }

    public function disable(?string $updatedBy = null, ?DateTimeImmutable $at = null): MaintenanceState
    {
        $at ??= $this->clock->now();
        $state = $this->stateStorage->load()->withScheduledDisableAt(null);
        // A start that already passed would otherwise keep maintenance on with no end (scheduledEnableAt <= now and
        // no scheduledDisableAt); a future start is kept so a planned window still runs.
        $start = $state->getScheduledEnableAt();
        if ($start instanceof DateTimeImmutable && $start <= $at) {
            $state = $state->withScheduledEnableAt(null);
        }

        return $this->writeDisabled($state, $updatedBy, $at);
    }

    public function applySchedule(?DateTimeImmutable $now = null, ?string $actor = self::ACTOR_SCHEDULE): ?MaintenanceState
    {
        $now ??= $this->clock->now();
        $state     = $this->stateStorage->load();
        $effective = $state->isEffectivelyEnabled($now);

        if ($effective === $state->isEnabled()) {
            return null;
        }

        // Compare-before-save: a panel / CLI write that landed after the load wins, this tick does nothing (the next
        // one re-evaluates). Narrows the race; run the tick on one node only.
        if ($this->stateStorage->load()->toArray() !== $state->toArray()) {
            return null;
        }

        $context = ['trigger' => self::ACTOR_SCHEDULE];

        if ($effective) {
            // A window started (scheduledEnableAt <= now): enabled, activatedAt = the start, both bounds kept and
            // marked startedBySchedule, so the panel still shows the window and schedule() / clearSchedule() can move
            // or end it (see isScheduleOwned()).
            return $this->writeEnabled(
                $state,
                $state->getMessage() ?? $this->defaultMessage,
                $actor,
                $state->getScheduledEnableAt() ?? $now,
                $context,
                bySchedule: true,
            );
        }

        // A window ended (scheduledDisableAt <= now): once the end is in the past the stored schedule can never turn
        // maintenance on again, so both bounds are cleared (same effective state, now and later).
        $at = $state->getScheduledDisableAt() ?? $now;

        return $this->writeDisabled($state->withScheduledEnableAt(null)->withScheduledDisableAt(null), $actor, $at, $context);
    }

    public function schedule(
        ?DateTimeImmutable $enableAt = null,
        ?DateTimeImmutable $disableAt = null,
        ?string $message = null,
        ?string $updatedBy = null,
        bool $clearMissing = false,
    ): MaintenanceState {
        $state = $this->stateStorage->load();

        if ($enableAt instanceof DateTimeImmutable || $clearMissing) {
            $state = $state->withScheduledEnableAt($enableAt);
        }
        if ($disableAt instanceof DateTimeImmutable || $clearMissing) {
            $state = $state->withScheduledDisableAt($disableAt);
        }
        if ($message !== null && $message !== '') {
            $state = $state->withMessage($message);
        }

        return $this->writeSchedule($state, $updatedBy, 'schedule');
    }

    public function clearSchedule(?string $updatedBy = null): MaintenanceState
    {
        $state = $this->stateStorage->load()
            ->withScheduledEnableAt(null)
            ->withScheduledDisableAt(null);

        return $this->writeSchedule($state, $updatedBy, 'clear_schedule');
    }

    /**
     * Whether the stored `enabled` flag was set by {@see applySchedule()}: the tick stores the explicit
     * `startedBySchedule` marker, which enable() and disable() clear. No timestamp comparison, so a manual enable at
     * the same second as a later start (KH-M-L5) or a start moved within the past (KH-M-L6) is classified correctly.
     * A state written before 1.16.1 has no marker and reads as manual (fail-closed).
     */
    private function isScheduleOwned(MaintenanceState $state): bool
    {
        return $state->isStartedBySchedule();
    }

    /**
     * schedule() / clearSchedule(): when the tick had switched maintenance on for the window being edited, the flag
     * follows the new bounds (on only while the new window covers now), so clearing or moving a running window ends
     * it, as it does without the tick. Ending it dispatches MaintenanceDisabledEvent (actor = the editor).
     */
    private function writeSchedule(MaintenanceState $state, ?string $updatedBy, string $action): MaintenanceState
    {
        $base = $this->stateStorage->load();
        if (!$this->isScheduleOwned($base)) {
            return $this->update($state, $updatedBy, $action);
        }

        $now       = $this->clock->now();
        $candidate = $state->withEnabled(false);
        if ($candidate->isEffectivelyEnabled($now)) {
            // Still inside the (edited) window: stays on, still owned by the schedule (the marker is kept; activatedAt
            // stays the instant maintenance really went on, even when the start moved).
            return $this->update($state, $updatedBy, $action);
        }

        $state = $this->update($candidate->withDeactivatedAt($now), $updatedBy, $action);
        $this->eventDispatcher?->dispatch(new MaintenanceDisabledEvent($state, $updatedBy));
        $this->logger->info('Maintenance mode disabled.', $this->logContext($action, $updatedBy));

        return $state;
    }

    public function update(
        MaintenanceState $state,
        ?string $updatedBy = null,
        string $action = 'update',
    ): MaintenanceState {
        $at = $this->clock->now();
        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache
        $state = $state->withUpdatedBy($updatedBy);
        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache
        $this->stateStorage->save($state);
        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache
        $this->historyStorage->append(new MaintenanceHistoryEntry(
            action: $action,
            occurredAt: $at,
            actor: $updatedBy,
            message: $state->getMessage(),
            context: $state->toArray(),
        ));
        $this->eventDispatcher?->dispatch(new MaintenanceUpdatedEvent($state, $action, $updatedBy));
        $this->logger->info('Maintenance mode state updated.', $this->logContext($action, $updatedBy));

        return $state;
    }

    /**
     * @return list<MaintenanceHistoryEntry>
     */
    public function history(int $limit = 50): array
    {
        return $this->historyStorage->list($limit);
    }

    /**
     * @param array<string, mixed> $context History entry context
     */
    private function writeEnabled(MaintenanceState $base, ?string $message, ?string $updatedBy, DateTimeImmutable $at, array $context = [], bool $bySchedule = false): MaintenanceState
    {
        // bySchedule: the tick keeps the window's start and marks the state; a manual enable clears both.
        $state = $base
            ->withEnabled(true)
            ->withStartedBySchedule($bySchedule)
            ->withActivatedAt($at)
            ->withDeactivatedAt(null)
            ->withMessage($message)
            ->withUpdatedBy($updatedBy);
        if (!$bySchedule) {
            $state = $state->withScheduledEnableAt(null);
        }

        $this->stateStorage->save($state);
        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache
        $this->historyStorage->append(new MaintenanceHistoryEntry(
            action: 'enable',
            occurredAt: $at,
            actor: $updatedBy,
            message: $state->getMessage(),
            context: $context,
        ));
        $this->eventDispatcher?->dispatch(new MaintenanceEnabledEvent($state, $updatedBy));
        $this->logger->info('Maintenance mode enabled.', $this->logContext('enable', $updatedBy));

        return $state;
    }

    /**
     * @param array<string, mixed> $context History entry context
     */
    private function writeDisabled(MaintenanceState $base, ?string $updatedBy, DateTimeImmutable $at, array $context = []): MaintenanceState
    {
        $state = $base
            ->withEnabled(false)
            ->withDeactivatedAt($at)
            ->withUpdatedBy($updatedBy);

        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache
        $this->stateStorage->save($state);
        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache
        $this->historyStorage->append(new MaintenanceHistoryEntry(
            action: 'disable',
            occurredAt: $at,
            actor: $updatedBy,
            message: $state->getMessage(),
            context: $context,
        ));
        $this->eventDispatcher?->dispatch(new MaintenanceDisabledEvent($state, $updatedBy));
        $this->logger->info('Maintenance mode disabled.', $this->logContext('disable', $updatedBy));

        return $state;
    }

    /**
     * Structured log context; the actor name is not logged (only whether one was given).
     *
     * @return array{bundle: string, action: string, by_user: bool}
     */
    private function logContext(string $action, ?string $updatedBy): array
    {
        return ['bundle' => 'maintenance_mode', 'action' => $action, 'by_user' => $updatedBy !== null && $updatedBy !== ''];
    }
}
