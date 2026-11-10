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
        $state = $this->stateStorage->load()->withScheduledDisableAt(null);

        return $this->writeDisabled($state, $updatedBy, $at ?? $this->clock->now());
    }

    public function applySchedule(?DateTimeImmutable $now = null, ?string $actor = self::ACTOR_SCHEDULE): ?MaintenanceState
    {
        $now ??= $this->clock->now();
        $state     = $this->stateStorage->load();
        $effective = $state->isEffectivelyEnabled($now);

        if ($effective === $state->isEnabled()) {
            return null;
        }

        $context = ['trigger' => self::ACTOR_SCHEDULE];

        if ($effective) {
            // A window started (scheduledEnableAt <= now): enabled, the start cleared, the end (if any) kept.
            return $this->writeEnabled(
                $state,
                $state->getMessage() ?? $this->defaultMessage,
                $actor,
                $state->getScheduledEnableAt() ?? $now,
                $context,
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

        return $this->update($state, $updatedBy, 'schedule');
    }

    public function clearSchedule(?string $updatedBy = null): MaintenanceState
    {
        $state = $this->stateStorage->load()
            ->withScheduledEnableAt(null)
            ->withScheduledDisableAt(null);

        return $this->update($state, $updatedBy, 'clear_schedule');
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
    private function writeEnabled(MaintenanceState $base, ?string $message, ?string $updatedBy, DateTimeImmutable $at, array $context = []): MaintenanceState
    {
        $state = $base
            ->withEnabled(true)
            ->withActivatedAt($at)
            ->withDeactivatedAt(null)
            ->withMessage($message)
            ->withUpdatedBy($updatedBy)
            ->withScheduledEnableAt(null);

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
