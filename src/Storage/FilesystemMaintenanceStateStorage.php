<?php

declare(strict_types=1);

namespace Nowo\MaintenanceModeBundle\Storage;

use Nowo\MaintenanceModeBundle\Model\MaintenanceState;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

use function dirname;
use function file_exists;
use function file_get_contents;
use function file_put_contents;
use function is_array;
use function is_dir;
use function json_decode;
use function json_encode;
use function mkdir;
use function rename;
use function sprintf;
use function str_ends_with;
use function tempnam;
use function unlink;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

/**
 * Default filesystem backend (JSON or YAML) under var/.
 */
final class FilesystemMaintenanceStateStorage implements MaintenanceStateStorageInterface
{
    public function __construct(
        private readonly string $filePath,
    ) {
    }

    public function load(): MaintenanceState
    {
        if (!file_exists($this->filePath)) {
            return new MaintenanceState();
        }

        $raw = file_get_contents($this->filePath);
        if ($raw === false || $raw === '') {
            return new MaintenanceState();
        }

        $data = $this->decode($raw);
        if (!is_array($data)) {
            return new MaintenanceState();
        }

        /* @var array<string, mixed> $data */
        return MaintenanceState::fromArray($data);
    }

    public function save(MaintenanceState $state): void
    {
        $directory = dirname($this->filePath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf('Unable to create maintenance state directory "%s".', $directory));
            // @codeCoverageIgnoreEnd
        }

        $payload = $this->encode($state->toArray());
        $tmp     = tempnam($directory, 'mm_state_');
        if ($tmp === false) {
            // @codeCoverageIgnoreStart
            throw new RuntimeException(sprintf('Unable to create temp file for "%s".', $this->filePath));
            // @codeCoverageIgnoreEnd
        }

        if (file_put_contents($tmp, $payload) === false) {
            // @codeCoverageIgnoreStart
            unlink($tmp);
            throw new RuntimeException(sprintf('Unable to write maintenance state to "%s".', $this->filePath));
            // @codeCoverageIgnoreEnd
        }

        if (!rename($tmp, $this->filePath)) {
            // @codeCoverageIgnoreStart
            unlink($tmp);
            throw new RuntimeException(sprintf('Unable to move maintenance state to "%s".', $this->filePath));
            // @codeCoverageIgnoreEnd
        }
    }

    /**
     * @return array<mixed>|null
     */
    private function decode(string $raw): ?array
    {
        if (str_ends_with($this->filePath, '.yaml') || str_ends_with($this->filePath, '.yml')) {
            $parsed = Yaml::parse($raw);

            return is_array($parsed) ? $parsed : null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function encode(array $data): string
    {
        if (str_ends_with($this->filePath, '.yaml') || str_ends_with($this->filePath, '.yml')) {
            return Yaml::dump($data, 4, 2);
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    }
}
