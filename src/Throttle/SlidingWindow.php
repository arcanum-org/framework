<?php

declare(strict_types=1);

namespace Arcanum\Throttle;

use Psr\SimpleCache\CacheInterface;

/**
 * Sliding window rate limiter.
 *
 * Tracks request counts in fixed windows and weights the previous
 * window's count by its overlap with the current sliding window.
 * Provides a smooth rate limit with no burst allowance.
 *
 * Cache entries:
 * - {key}_cur => ['count' => int, 'windowStart' => int]
 * - {key}_prev => ['count' => int, 'windowStart' => int]
 */
final class SlidingWindow implements Throttler
{
    public function attempt(CacheInterface $cache, string $key, int $limit, int $windowSeconds): Quota
    {
        $now = time();

        $curKey = $key . '_cur';
        $prevKey = $key . '_prev';

        /** @var array{count: int, windowStart: int}|null $current */
        $current = $cache->get($curKey);

        /** @var array{count: int, windowStart: int}|null $previous */
        $previous = $cache->get($prevKey);

        if ($current === null) {
            $current = ['count' => 0, 'windowStart' => $now];
        }

        // Rotate: if the current window has expired, shift it to previous.
        if ($now - $current['windowStart'] >= $windowSeconds) {
            $previous = $current;
            $cache->set($prevKey, $previous, $windowSeconds * 2);

            $current = ['count' => 0, 'windowStart' => $now];
        }

        // Weight the previous window by how much of it overlaps.
        $previousCount = 0;
        if ($previous !== null) {
            $previousWindowEnd = $previous['windowStart'] + $windowSeconds;
            $overlap = max(0, $previousWindowEnd - $now);
            $weight = $overlap / $windowSeconds;
            $previousCount = (int) floor($previous['count'] * $weight);
        }

        $estimatedCount = $previousCount + $current['count'];

        if ($estimatedCount >= $limit) {
            $resetAt = $current['windowStart'] + $windowSeconds;

            return new Quota(false, 0, $limit, $resetAt);
        }

        $current['count']++;
        $cache->set($curKey, $current, $windowSeconds);

        $remaining = $limit - ($previousCount + $current['count']);
        $resetAt = $current['windowStart'] + $windowSeconds;

        return new Quota(true, $remaining, $limit, $resetAt);
    }
}
