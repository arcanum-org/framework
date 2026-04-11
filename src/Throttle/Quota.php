<?php

declare(strict_types=1);

namespace Arcanum\Throttle;

/**
 * Immutable result of a rate-limit attempt.
 *
 * Carries whether the request was allowed, how many requests remain,
 * the configured limit, when the window resets, and (when denied) how many
 * seconds the caller should wait before retrying.
 *
 * Quota stays a pure value object — it does not depend on Hourglass\Clock.
 * The Throttler that constructs the Quota holds the Clock and computes
 * `retryAfter` once at construction time, so `headers()` can render
 * `Retry-After` deterministically without re-reading "now".
 */
final class Quota
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $remaining,
        public readonly int $limit,
        public readonly int $resetAt,
        public readonly int $retryAfter = 0,
    ) {
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    /**
     * Rate-limit headers for the response.
     *
     * Always includes X-RateLimit-Limit, X-RateLimit-Remaining, and
     * X-RateLimit-Reset. Adds Retry-After when the request was denied.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = [
            'X-RateLimit-Limit' => (string) $this->limit,
            'X-RateLimit-Remaining' => (string) max(0, $this->remaining),
            'X-RateLimit-Reset' => (string) $this->resetAt,
        ];

        if (! $this->allowed) {
            $headers['Retry-After'] = (string) max(0, $this->retryAfter);
        }

        return $headers;
    }
}
