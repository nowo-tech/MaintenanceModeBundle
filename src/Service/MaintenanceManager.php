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
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Application service for enabling / disabling / scheduling maintenance.
 */
final class MaintenanceManager
{
    public function __construct(
        private readonly MaintenanceStateStorageInterface $stateStorage,
        private readonly MaintenanceHistoryStorageInterface $historyStorage,
        private readonly ?string $defaultMessage = null,
        private readonly ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public function getState(): MaintenanceState
    {
        return $this->stateStorage->load();
    }

    public function isEffectivelyEnabled(?DateTimeImmutable $now = null): bool
    {
        return $this->getState()->isEffectivelyEnabled($now);
    }

    public function enable(?string $message = null, ?string $updatedBy = null, ?DateTimeImmutable $at = null): MaintenanceState
    {
        $at ??= new DateTimeImmutable();
        $state = $this->stateStorage->load()
            ->withEnabled(true)
            ->withActivatedAt($at)
            ->withDeactivatedAt(null)
            ->withMessage($message ?? $this->defaultMessage)
            ->withUpdatedBy($updatedBy)
            ->withScheduledEnableAt(null);

        $this->stateStorage->save($state);
        $this->historyStorage->append(new MaintenanceHistoryEntry(
            action: 'enable',
            occurredAt: $at,
            actor: $updatedBy,
            message: $state->getMessage(),
        ));
        $this->eventDispatcher?->dispatch(new MaintenanceEnabledEvent($state, $updatedBy));

        return $state;
    }

    public function disable(?string $updatedBy = null, ?DateTimeImmutable $at = null): MaintenanceState
    {
        $at ??= new DateTimeImmutable();
        $state = $this->stateStorage->load()
            ->withEnabled(false)
            ->withDeactivatedAt($at)
            ->withUpdatedBy($updatedBy)
            ->withScheduledDisableAt(null);

        $this->stateStorage->save($state);
        $this->historyStorage->append(new MaintenanceHistoryEntry(
            action: 'disable',
            occurredAt: $at,
            actor: $updatedBy,
            message: $state->getMessage(),
        ));
        $this->eventDispatcher?->dispatch(new MaintenanceDisabledEvent($state, $updatedBy));

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
        $at    = new DateTimeImmutable();
        $state = $state->withUpdatedBy($updatedBy);
        $this->stateStorage->save($state);
        $this->historyStorage->append(new MaintenanceHistoryEntry(
            action: $action,
            occurredAt: $at,
            actor: $updatedBy,
            message: $state->getMessage(),
            context: $state->toArray(),
        ));
        $this->eventDispatcher?->dispatch(new MaintenanceUpdatedEvent($state, $action, $updatedBy));

        return $state;
    }

    /**
     * @return list<MaintenanceHistoryEntry>
     */
    public function history(int $limit = 50): array
    {
        return $this->historyStorage->list($limit);
    }
}
