<?php

declare(strict_types=1);

namespace Arcanum\Throttle;

use Psr\SimpleCache\CacheInterface;

/**
 * Main entry point for rate limiting.
 *
 * Wraps a cache and a throttling strategy. Application code calls
 * attempt() with a key (e.g. IP address) and gets back a Quota
 * describing whether the request is allowed and what headers to send.
 */
final class RateLimiter
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly Throttler $throttler = new TokenBucket(),
    ) {
    }

    /**
     * Check and record a rate-limit attempt.
     *
     * @param positive-int $limit Maximum requests allowed per window.
     * @param positive-int $windowSeconds Window duration in seconds.
     */
    public function attempt(string $key, int $limit, int $windowSeconds): Quota
    {
        return $this->throttler->attempt($this->cache, $key, $limit, $windowSeconds);
    }
}
