<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Tests\Unit\Storage;

use DateTimeImmutable;
use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use Nowo\MaintenanceModeBundle\Storage\FilesystemMaintenanceHistoryStorage;
use Nowo\MaintenanceModeBundle\Storage\FilesystemMaintenanceStateStorage;
use PHPUnit\Framework\TestCase;

use function sys_get_temp_dir;
use function uniqid;

final class FilesystemStorageTest extends TestCase
{
    public function testStateSaveAndLoad(): void
    {
        $path    = sys_get_temp_dir() . '/mm_state_' . uniqid('', true) . '.json';
        $storage = new FilesystemMaintenanceStateStorage($path);

        $storage->save((new MaintenanceState())->withEnabled(true)->withMessage('Hi'));
        $loaded = $storage->load();

        self::assertTrue($loaded->isEnabled());
        self::assertSame('Hi', $loaded->getMessage());

        @unlink($path);
    }

    public function testHistoryAppendOnly(): void
    {
        $path    = sys_get_temp_dir() . '/mm_hist_' . uniqid('', true) . '.jsonl';
        $storage = new FilesystemMaintenanceHistoryStorage($path);

        $storage->append(new MaintenanceHistoryEntry('enable', new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'a'));
        $storage->append(new MaintenanceHistoryEntry('disable', new DateTimeImmutable('2026-01-02T00:00:00+00:00'), 'b'));

        $entries = $storage->list(10);
        self::assertCount(2, $entries);
        self::assertSame('disable', $entries[0]->getAction());
        self::assertSame('enable', $entries[1]->getAction());

        @unlink($path);
    }

    public function testStateSaveAndLoadYaml(): void
    {
        $path    = sys_get_temp_dir() . '/mm_state_' . uniqid('', true) . '.yaml';
        $storage = new FilesystemMaintenanceStateStorage($path);

        $storage->save((new MaintenanceState())->withEnabled(true)->withMessage('YAML'));
        $loaded = $storage->load();

        self::assertTrue($loaded->isEnabled());
        self::assertSame('YAML', $loaded->getMessage());

        @unlink($path);
    }

    public function testStateLoadReturnsDefaultWhenFileMissing(): void
    {
        $path    = sys_get_temp_dir() . '/mm_missing_' . uniqid('', true) . '.json';
        $storage = new FilesystemMaintenanceStateStorage($path);

        self::assertFalse($storage->load()->isEnabled());
    }

    public function testStateLoadReturnsDefaultWhenFileEmptyOrCorrupt(): void
    {
        $path = sys_get_temp_dir() . '/mm_bad_' . uniqid('', true) . '.json';
        file_put_contents($path, '');

        $storage = new FilesystemMaintenanceStateStorage($path);
        self::assertFalse($storage->load()->isEnabled());

        file_put_contents($path, 'not-json');
        self::assertFalse($storage->load()->isEnabled());

        @unlink($path);
    }

    public function testHistoryListReturnsEmptyWhenLimitBelowOne(): void
    {
        $path    = sys_get_temp_dir() . '/mm_hist_' . uniqid('', true) . '.jsonl';
        $storage = new FilesystemMaintenanceHistoryStorage($path);

        $storage->append(new MaintenanceHistoryEntry('enable', new DateTimeImmutable(), 'a'));

        self::assertSame([], $storage->list(0));

        @unlink($path);
    }

    public function testHistorySkipsInvalidLines(): void
    {
        $path = sys_get_temp_dir() . '/mm_hist_' . uniqid('', true) . '.jsonl';
        file_put_contents($path, "not-json\n" . json_encode([
            'action'      => 'enable',
            'occurred_at' => '2026-01-01T00:00:00+00:00',
        ]) . "\n");

        $storage = new FilesystemMaintenanceHistoryStorage($path);
        $entries = $storage->list(10);

        self::assertCount(1, $entries);
        self::assertSame('enable', $entries[0]->getAction());

        @unlink($path);
    }

    public function testStateSaveAndLoadYmlExtension(): void
    {
        $path    = sys_get_temp_dir() . '/mm_state_' . uniqid('', true) . '.yml';
        $storage = new FilesystemMaintenanceStateStorage($path);

        $storage->save((new MaintenanceState())->withEnabled(true)->withMessage('YML'));
        $loaded = $storage->load();

        self::assertTrue($loaded->isEnabled());
        self::assertSame('YML', $loaded->getMessage());

        @unlink($path);
    }

    public function testHistoryListReturnsEmptyWhenFileMissingOrEmpty(): void
    {
        $missing = sys_get_temp_dir() . '/mm_hist_missing_' . uniqid('', true) . '.jsonl';
        $storage = new FilesystemMaintenanceHistoryStorage($missing);
        self::assertSame([], $storage->list(10));

        file_put_contents($missing, '');
        self::assertSame([], $storage->list(10));

        @unlink($missing);
    }
}
