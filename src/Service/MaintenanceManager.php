<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Service;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceHistoryStorageInterface;
use Nowo\MaintenanceModeBundle\Storage\MaintenanceStateStorageInterface;

/**
 * Application service for enabling / disabling / scheduling maintenance.
 */
final class MaintenanceManager
{
    public function __construct(
        private readonly MaintenanceStateStorageInterface $stateStorage,
        private readonly MaintenanceHistoryStorageInterface $historyStorage,
        private readonly ?string $defaultMessage = null,
    ) {
    }

    public function getState(): MaintenanceState
    {
        return $this->stateStorage->load();
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

        return $state;
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
