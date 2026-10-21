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
 * Application service for enabling / disabling / scheduling maintenance.
 */
final class MaintenanceManager
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
        $at ??= $this->clock->now();
        $state = $this->stateStorage->load()
            ->withEnabled(true)
            ->withActivatedAt($at)
            ->withDeactivatedAt(null)
            ->withMessage($message ?? $this->defaultMessage)
            ->withUpdatedBy($updatedBy)
            ->withScheduledEnableAt(null);

        $this->stateStorage->save($state);
        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache
        $this->historyStorage->append(new MaintenanceHistoryEntry(
            action: 'enable',
            occurredAt: $at,
            actor: $updatedBy,
            message: $state->getMessage(),
        ));
        $this->eventDispatcher?->dispatch(new MaintenanceEnabledEvent($state, $updatedBy));
        $this->logger->info('Maintenance mode enabled.', $this->logContext('enable', $updatedBy));

        return $state;
    }

    public function disable(?string $updatedBy = null, ?DateTimeImmutable $at = null): MaintenanceState
    {
        $at ??= $this->clock->now();
        $state = $this->stateStorage->load()
            ->withEnabled(false)
            ->withDeactivatedAt($at)
            ->withUpdatedBy($updatedBy)
            ->withScheduledDisableAt(null);

        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache
        $this->stateStorage->save($state);
        // @igor-ignore - Mutates attached request-scoped or value object; not worker singleton cache
        $this->historyStorage->append(new MaintenanceHistoryEntry(
            action: 'disable',
            occurredAt: $at,
            actor: $updatedBy,
            message: $state->getMessage(),
        ));
        $this->eventDispatcher?->dispatch(new MaintenanceDisabledEvent($state, $updatedBy));
        $this->logger->info('Maintenance mode disabled.', $this->logContext('disable', $updatedBy));

        return $state;
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
     * Structured log context; the actor name is not logged (only whether one was given).
     *
     * @return array{bundle: string, action: string, by_user: bool}
     */
    private function logContext(string $action, ?string $updatedBy): array
    {
        return ['bundle' => 'maintenance_mode', 'action' => $action, 'by_user' => $updatedBy !== null && $updatedBy !== ''];
    }
}
