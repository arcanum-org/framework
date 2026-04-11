<?php

declare(strict_types=1);

namespace Arcanum\Session;

/**
 * Persistence backend for session data.
 *
 * Drivers store and retrieve session payloads keyed by session ID.
 * The payload is an associative array managed by the Session object;
 * drivers are responsible only for serialization, storage, and expiry.
 */
interface SessionDriver
{
    /**
     * Read session data for the given ID.
     *
     * Returns an empty array if the session does not exist or has expired.
     *
     * @return array<string, mixed>
     */
    public function read(string $id): array;

    /**
     * Write session data for the given ID.
     *
     * @param array<string, mixed> $data
     * @param positive-int $ttl Lifetime in seconds.
     */
    public function write(string $id, array $data, int $ttl): void;

    /**
     * Destroy a session by ID.
     */
    public function destroy(string $id): void;
}
