<?php

declare(strict_types=1);

namespace Arcanum\Throttle;

use Psr\SimpleCache\CacheInterface;

/**
 * Token bucket rate limiter.
 *
 * Tokens refill at a steady rate up to the configured limit.
 * Each request costs one token. Allows controlled bursts — a client
 * that has been idle accumulates tokens up to the maximum.
 *
 * Cache entry: ['tokens' => float, 'lastRefill' => int]
 */
final class TokenBucket implements Throttler
{
    public function attempt(CacheInterface $cache, string $key, int $limit, int $windowSeconds): Quota
    {
        $now = time();
        $refillRate = $limit / $windowSeconds;

        /** @var array{tokens: float, lastRefill: int}|null $bucket */
        $bucket = $cache->get($key);

        if ($bucket === null) {
            $bucket = ['tokens' => (float) $limit, 'lastRefill' => $now];
        }

        $elapsed = $now - $bucket['lastRefill'];
        $tokens = min($limit, $bucket['tokens'] + ($elapsed * $refillRate));
        $lastRefill = $now;

        if ($tokens >= 1.0) {
            $tokens -= 1.0;
            $allowed = true;
        } else {
            $allowed = false;
        }

        $cache->set($key, ['tokens' => $tokens, 'lastRefill' => $lastRefill], $windowSeconds);

        $remaining = (int) floor($tokens);
        $resetAt = $allowed
            ? $now + $windowSeconds
            : $now + (int) ceil((1.0 - $tokens) / $refillRate);

        return new Quota($allowed, $remaining, $limit, $resetAt);
    }
}
