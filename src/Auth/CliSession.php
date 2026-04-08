<?php

declare(strict_types=1);

namespace Arcanum\Auth;

use Arcanum\Hourglass\Clock;
use Arcanum\Hourglass\SystemClock;
use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Toolkit\Encryption\Encryptor;

/**
 * Encrypted file-based credential store for CLI sessions.
 *
 * Stores an identity ID and expiry timestamp, encrypted at rest via
 * the framework's Encryptor (APP_KEY). This lets CLI users run
 * `login` once and stay authenticated for the TTL duration without
 * passing --token on every command.
 */
class CliSession
{
    public function __construct(
        private readonly Encryptor $encryptor,
        private readonly string $path,
        private readonly Reader $reader = new Reader(),
        private readonly Writer $writer = new Writer(),
        private readonly FileSystem $fileSystem = new FileSystem(),
        private readonly Clock $clock = new SystemClock(),
    ) {
    }

    /**
     * Store an identity ID with a TTL.
     */
    public function store(string $identityId, int $ttl): void
    {
        $payload = json_encode([
            'id' => $identityId,
            'expires' => $this->clock->now()->getTimestamp() + $ttl,
        ], \JSON_THROW_ON_ERROR);

        $encrypted = $this->encryptor->encrypt($payload);

        $dir = dirname($this->path);
        if (!$this->fileSystem->isDirectory($dir)) {
            $this->fileSystem->mkdir($dir);
        }

        $this->writer->write($this->path, $encrypted);
    }

    /**
     * Load the stored identity ID, or null if absent/expired/corrupt.
     *
     * Deletes the file if expired or corrupt.
     */
    public function load(): string|null
    {
        if (!$this->fileSystem->isFile($this->path)) {
            return null;
        }

        try {
            $encrypted = $this->reader->read($this->path);
            $json = $this->encryptor->decrypt($encrypted);

            /** @var array<string, mixed> $data */
            $data = json_decode($json, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->clear();
            return null;
        }

        $id = $data['id'] ?? null;
        $expires = $data['expires'] ?? 0;

        if (!is_string($id) || !is_int($expires) || $expires <= $this->clock->now()->getTimestamp()) {
            $this->clear();
            return null;
        }

        return $id;
    }

    /**
     * Delete the session file.
     */
    public function clear(): void
    {
        if ($this->fileSystem->isFile($this->path)) {
            $this->fileSystem->delete($this->path);
        }
    }
}
