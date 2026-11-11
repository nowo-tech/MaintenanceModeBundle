<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Model;

use DateTimeImmutable;
use Exception;

use function is_string;

use const DATE_ATOM;

/**
 * Immutable snapshot of the site maintenance mode state: every with*() method returns a modified copy.
 */
final class MaintenanceState
{
    public function __construct(
        private bool $enabled = false,
        private ?DateTimeImmutable $activatedAt = null,
        private ?DateTimeImmutable $deactivatedAt = null,
        private ?DateTimeImmutable $scheduledEnableAt = null,
        private ?DateTimeImmutable $scheduledDisableAt = null,
        private ?string $message = null,
        private ?string $updatedBy = null,
        private bool $startedBySchedule = false,
    ) {
        // The marker only means something while maintenance is on.
        $this->startedBySchedule = $enabled && $startedBySchedule;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getActivatedAt(): ?DateTimeImmutable
    {
        return $this->activatedAt;
    }

    public function getDeactivatedAt(): ?DateTimeImmutable
    {
        return $this->deactivatedAt;
    }

    public function getScheduledEnableAt(): ?DateTimeImmutable
    {
        return $this->scheduledEnableAt;
    }

    public function getScheduledDisableAt(): ?DateTimeImmutable
    {
        return $this->scheduledDisableAt;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    /**
     * Whether the stored `enabled` flag was set by the schedule tick (MaintenanceManagerInterface::applySchedule())
     * rather than by a manual enable. Always false while maintenance is off.
     */
    public function isStartedBySchedule(): bool
    {
        return $this->startedBySchedule;
    }

    public function withEnabled(bool $enabled): self
    {
        $clone          = clone $this;
        $clone->enabled = $enabled;
        if (!$enabled) {
            $clone->startedBySchedule = false;
        }

        return $clone;
    }

    /**
     * Ignored (stays false) while maintenance is off.
     */
    public function withStartedBySchedule(bool $startedBySchedule): self
    {
        $clone                    = clone $this;
        $clone->startedBySchedule = $clone->enabled && $startedBySchedule;

        return $clone;
    }

    public function withActivatedAt(?DateTimeImmutable $activatedAt): self
    {
        $clone              = clone $this;
        $clone->activatedAt = $activatedAt;

        return $clone;
    }

    public function withDeactivatedAt(?DateTimeImmutable $deactivatedAt): self
    {
        $clone                = clone $this;
        $clone->deactivatedAt = $deactivatedAt;

        return $clone;
    }

    public function withScheduledEnableAt(?DateTimeImmutable $scheduledEnableAt): self
    {
        $clone                    = clone $this;
        $clone->scheduledEnableAt = $scheduledEnableAt;

        return $clone;
    }

    public function withScheduledDisableAt(?DateTimeImmutable $scheduledDisableAt): self
    {
        $clone                     = clone $this;
        $clone->scheduledDisableAt = $scheduledDisableAt;

        return $clone;
    }

    public function withMessage(?string $message): self
    {
        $clone          = clone $this;
        $clone->message = $message;

        return $clone;
    }

    public function withUpdatedBy(?string $updatedBy): self
    {
        $clone            = clone $this;
        $clone->updatedBy = $updatedBy;

        return $clone;
    }

    /**
     * Whether maintenance should be active right now, honouring schedules.
     */
    public function isEffectivelyEnabled(?DateTimeImmutable $now = null): bool
    {
        $now ??= new DateTimeImmutable();

        if ($this->scheduledEnableAt instanceof DateTimeImmutable
            && $this->scheduledDisableAt instanceof DateTimeImmutable
            && $this->scheduledEnableAt <= $now
            && $this->scheduledDisableAt > $now
        ) {
            return true;
        }

        if ($this->scheduledEnableAt instanceof DateTimeImmutable
            && !$this->scheduledDisableAt instanceof DateTimeImmutable
            && $this->scheduledEnableAt <= $now
        ) {
            return true;
        }

        if ($this->scheduledDisableAt instanceof DateTimeImmutable
            && $this->enabled
            && $this->scheduledDisableAt <= $now
        ) {
            return false;
        }

        return $this->enabled;
    }

    /**
     * @return array{
     *     enabled: bool,
     *     activated_at: ?string,
     *     deactivated_at: ?string,
     *     scheduled_enable_at: ?string,
     *     scheduled_disable_at: ?string,
     *     message: ?string,
     *     updated_by: ?string,
     *     started_by_schedule: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'enabled'              => $this->enabled,
            'activated_at'         => $this->activatedAt?->format(DATE_ATOM),
            'deactivated_at'       => $this->deactivatedAt?->format(DATE_ATOM),
            'scheduled_enable_at'  => $this->scheduledEnableAt?->format(DATE_ATOM),
            'scheduled_disable_at' => $this->scheduledDisableAt?->format(DATE_ATOM),
            'message'              => $this->message,
            'updated_by'           => $this->updatedBy,
            'started_by_schedule'  => $this->startedBySchedule,
        ];
    }

    /**
     * A state written before 1.16.1 has no `started_by_schedule` key and reads as a manual enable (fail-closed: a
     * schedule edit then never switches it off; Disable does).
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            enabled: (bool) ($data['enabled'] ?? false),
            activatedAt: self::parseDate($data['activated_at'] ?? null),
            deactivatedAt: self::parseDate($data['deactivated_at'] ?? null),
            scheduledEnableAt: self::parseDate($data['scheduled_enable_at'] ?? null),
            scheduledDisableAt: self::parseDate($data['scheduled_disable_at'] ?? null),
            message: isset($data['message']) && is_string($data['message']) ? $data['message'] : null,
            updatedBy: isset($data['updated_by']) && is_string($data['updated_by']) ? $data['updated_by'] : null,
            startedBySchedule: ($data['started_by_schedule'] ?? false) === true,
        );
    }

    private static function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (!is_string($value)) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(DATE_ATOM, $value);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }
}
