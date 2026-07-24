<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Storage;

use Nowo\MaintenanceModeBundle\Model\MaintenanceHistoryEntry;
use RuntimeException;

use function array_filter;
use function array_reverse;
use function array_slice;
use function array_values;
use function dirname;
use function explode;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function sprintf;
use function trim;

use const FILE_APPEND;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const LOCK_EX;

/**
 * Append-only JSONL history file.
 */
final class FilesystemMaintenanceHistoryStorage implements MaintenanceHistoryStorageInterface
{
    public function __construct(
        private readonly string $filePath,
    ) {
    }

    public function append(MaintenanceHistoryEntry $entry): void
    {
        $directory = dirname($this->filePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf('Unable to create maintenance history directory "%s".', $directory));
            // @codeCoverageIgnoreEnd
        }

        $line = json_encode($entry->toArray(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

        if (file_put_contents($this->filePath, $line, FILE_APPEND | LOCK_EX) === false) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf('Unable to append maintenance history to "%s".', $this->filePath));
            // @codeCoverageIgnoreEnd
        }
    }

    public function list(int $limit = 50): array
    {
        if ($limit < 1) {
            return [];
        }

        if (!file_exists($this->filePath)) {
            return [];
        }

        $raw = file_get_contents($this->filePath);
        if ($raw === false || $raw === '') {
            return [];
        }

        $lines = array_values(array_filter(
            explode("\n", $raw),
            static fn (string $line): bool => trim($line) !== '',
        ));

        $entries = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            if (!is_array($decoded)) {
                continue;
            }

            /* @var array<string, mixed> $decoded */
            $entries[] = MaintenanceHistoryEntry::fromArray($decoded);
        }

        $entries = array_reverse($entries);

        return array_slice($entries, 0, $limit);
    }
}
