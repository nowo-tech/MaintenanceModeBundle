<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Model;

use DateTimeImmutable;

use function is_array;
use function is_string;

use const DATE_ATOM;

/**
 * Append-only history record for enable / disable / schedule actions.
 */
final class MaintenanceHistoryEntry
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        private readonly string $action,
        private readonly DateTimeImmutable $occurredAt,
        private readonly ?string $actor = null,
        private readonly ?string $message = null,
        private readonly array $context = [],
    ) {
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getOccurredAt(): DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getActor(): ?string
    {
        return $this->actor;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @return array{
     *     action: string,
     *     occurred_at: string,
     *     actor: ?string,
     *     message: ?string,
     *     context: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'action'      => $this->action,
            'occurred_at' => $this->occurredAt->format(DATE_ATOM),
            'actor'       => $this->actor,
            'message'     => $this->message,
            'context'     => $this->context,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $occurredAtRaw = $data['occurred_at'] ?? null;
        $occurredAt    = $occurredAtRaw instanceof DateTimeImmutable
            ? $occurredAtRaw
            : (is_string($occurredAtRaw) ? new DateTimeImmutable($occurredAtRaw) : new DateTimeImmutable());

        /** @var array<string, mixed> $context */
        $context = isset($data['context']) && is_array($data['context']) ? $data['context'] : [];

        return new self(
            action: is_string($data['action'] ?? null) ? $data['action'] : 'unknown',
            occurredAt: $occurredAt,
            actor: isset($data['actor']) && is_string($data['actor']) ? $data['actor'] : null,
            message: isset($data['message']) && is_string($data['message']) ? $data['message'] : null,
            context: $context,
        );
    }
}
