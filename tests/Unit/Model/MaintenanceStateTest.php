<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Model;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use PHPUnit\Framework\TestCase;

final class MaintenanceStateTest extends TestCase
{
    public function testRoundTripArray(): void
    {
        $state = (new MaintenanceState())
            ->withEnabled(true)
            ->withMessage('Down')
            ->withUpdatedBy('admin')
            ->withActivatedAt(new DateTimeImmutable('2026-01-01T10:00:00+00:00'));

        $restored = MaintenanceState::fromArray($state->toArray());

        self::assertTrue($restored->isEnabled());
        self::assertSame('Down', $restored->getMessage());
        self::assertSame('admin', $restored->getUpdatedBy());
        self::assertNotNull($restored->getActivatedAt());
    }

    public function testScheduledWindowIsEffectivelyEnabled(): void
    {
        $now   = new DateTimeImmutable('2026-07-24T12:00:00+00:00');
        $state = (new MaintenanceState())
            ->withEnabled(false)
            ->withScheduledEnableAt(new DateTimeImmutable('2026-07-24T11:00:00+00:00'))
            ->withScheduledDisableAt(new DateTimeImmutable('2026-07-24T13:00:00+00:00'));

        self::assertTrue($state->isEffectivelyEnabled($now));
        self::assertFalse($state->isEffectivelyEnabled(new DateTimeImmutable('2026-07-24T14:00:00+00:00')));
    }

    public function testScheduledEnableWithoutDisableIsEffectivelyEnabled(): void
    {
        $now   = new DateTimeImmutable('2026-07-24T12:00:00+00:00');
        $state = (new MaintenanceState())
            ->withEnabled(false)
            ->withScheduledEnableAt(new DateTimeImmutable('2026-07-24T11:00:00+00:00'));

        self::assertTrue($state->isEffectivelyEnabled($now));
    }

    public function testScheduledDisableWhenEnabledTurnsOffMaintenance(): void
    {
        $now   = new DateTimeImmutable('2026-07-24T12:00:00+00:00');
        $state = (new MaintenanceState())
            ->withEnabled(true)
            ->withScheduledDisableAt(new DateTimeImmutable('2026-07-24T11:00:00+00:00'));

        self::assertFalse($state->isEffectivelyEnabled($now));
    }

    public function testEnabledWithoutScheduleFollowsEnabledFlag(): void
    {
        $now = new DateTimeImmutable('2026-07-24T12:00:00+00:00');

        self::assertTrue((new MaintenanceState())->withEnabled(true)->isEffectivelyEnabled($now));
        self::assertFalse((new MaintenanceState())->withEnabled(false)->isEffectivelyEnabled($now));
    }

    public function testFromArrayParseDateEdgeCases(): void
    {
        $at = new DateTimeImmutable('2026-07-24T10:00:00+00:00');

        $fromImmutable = MaintenanceState::fromArray(['activated_at' => $at]);
        self::assertEquals($at, $fromImmutable->getActivatedAt());

        $fromAtom = MaintenanceState::fromArray(['activated_at' => '2026-07-24T10:00:00+00:00']);
        self::assertNotNull($fromAtom->getActivatedAt());

        $fromNull = MaintenanceState::fromArray(['activated_at' => null]);
        self::assertNull($fromNull->getActivatedAt());

        $fromEmpty = MaintenanceState::fromArray(['activated_at' => '']);
        self::assertNull($fromEmpty->getActivatedAt());

        $fromInvalid = MaintenanceState::fromArray(['activated_at' => 'not-a-date']);
        self::assertNull($fromInvalid->getActivatedAt());

        $fromNonString = MaintenanceState::fromArray(['activated_at' => 12345]);
        self::assertNull($fromNonString->getActivatedAt());
    }

    public function testFromArrayIgnoresNonStringMessageAndUpdatedBy(): void
    {
        $state = MaintenanceState::fromArray([
            'message'    => 123,
            'updated_by' => ['admin'],
        ]);

        self::assertNull($state->getMessage());
        self::assertNull($state->getUpdatedBy());
    }

    public function testWithMutatorsReturnClones(): void
    {
        $original = new MaintenanceState();
        $modified = $original
            ->withEnabled(true)
            ->withMessage('Hi')
            ->withUpdatedBy('admin')
            ->withActivatedAt(new DateTimeImmutable())
            ->withDeactivatedAt(new DateTimeImmutable())
            ->withScheduledEnableAt(new DateTimeImmutable())
            ->withScheduledDisableAt(new DateTimeImmutable());

        self::assertFalse($original->isEnabled());
        self::assertTrue($modified->isEnabled());
        self::assertSame('Hi', $modified->getMessage());
    }
}
