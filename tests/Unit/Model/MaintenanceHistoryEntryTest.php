<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Model;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use PHPUnit\Framework\TestCase;

final class MaintenanceHistoryEntryTest extends TestCase
{
    public function testGettersReturnConstructorValues(): void
    {
        $at    = new DateTimeImmutable('2026-07-24T12:00:00+00:00');
        $entry = new MaintenanceHistoryEntry(
            action: 'schedule',
            occurredAt: $at,
            actor: 'panel',
            message: 'Planned downtime',
            context: ['enabled' => true],
        );

        self::assertSame('schedule', $entry->getAction());
        self::assertEquals($at, $entry->getOccurredAt());
        self::assertSame('panel', $entry->getActor());
        self::assertSame('Planned downtime', $entry->getMessage());
        self::assertSame(['enabled' => true], $entry->getContext());
    }

    public function testToArrayRoundTrip(): void
    {
        $at       = new DateTimeImmutable('2026-07-24T12:00:00+00:00');
        $original = new MaintenanceHistoryEntry('enable', $at, 'ops', 'Down', ['foo' => 'bar']);

        $restored = MaintenanceHistoryEntry::fromArray($original->toArray());

        self::assertSame($original->getAction(), $restored->getAction());
        self::assertEquals($original->getOccurredAt(), $restored->getOccurredAt());
        self::assertSame($original->getActor(), $restored->getActor());
        self::assertSame($original->getMessage(), $restored->getMessage());
        self::assertSame($original->getContext(), $restored->getContext());
    }

    public function testFromArrayUsesDefaultsForMissingFields(): void
    {
        $entry = MaintenanceHistoryEntry::fromArray([]);

        self::assertSame('unknown', $entry->getAction());
        self::assertSame('2026', $entry->getOccurredAt()->format('Y'));
        self::assertNull($entry->getActor());
        self::assertNull($entry->getMessage());
        self::assertSame([], $entry->getContext());
    }

    public function testFromArrayAcceptsDateTimeImmutableOccurrence(): void
    {
        $at    = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $entry = MaintenanceHistoryEntry::fromArray(['occurred_at' => $at, 'action' => 'disable']);

        self::assertEquals($at, $entry->getOccurredAt());
        self::assertSame('disable', $entry->getAction());
    }

    public function testFromArrayIgnoresNonArrayContext(): void
    {
        $entry = MaintenanceHistoryEntry::fromArray(['context' => 'not-an-array']);

        self::assertSame([], $entry->getContext());
    }
}
