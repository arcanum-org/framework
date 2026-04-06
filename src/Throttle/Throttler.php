<?php

declare(strict_types=1);

namespace Arcanum\Throttle;

use Psr\SimpleCache\CacheInterface;

/**
 * Algorithm that decides whether a request should be allowed.
 *
 * Implementations store and retrieve state via a PSR-16 cache.
 * Each strategy encapsulates its own storage format and rate-limiting logic.
 */
interface Throttler
{
    /**
     * Check and record a rate-limit attempt.
     *
     * @param positive-int $limit Maximum requests allowed per window.
     * @param positive-int $windowSeconds Window duration in seconds.
     */
    public function attempt(CacheInterface $cache, string $key, int $limit, int $windowSeconds): Quota;
}
