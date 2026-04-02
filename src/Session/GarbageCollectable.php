<?php

declare(strict_types=1);

namespace Arcanum\Session;

/**
 * A session driver that requires explicit garbage collection.
 *
 * Drivers with native TTL support (cache-backed, client-side cookies)
 * don't need this. File-based drivers and other backends without
 * automatic expiry implement this interface so the middleware can
 * trigger cleanup periodically.
 */
interface GarbageCollectable
{
    /**
     * Remove expired sessions.
     *
     * @param positive-int $maxLifetime Sessions older than this (seconds) should be removed.
     */
    public function gc(int $maxLifetime): void;
}
