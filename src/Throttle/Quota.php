<?php

declare(strict_types=1);

namespace Arcanum\Throttle;

/**
 * Immutable result of a rate-limit attempt.
 *
 * Carries whether the request was allowed, how many requests remain,
 * the configured limit, and when the window resets.
 */
final class Quota
{
    public function __construct(
        public readonly bool $allowed,
        public readonly int $remaining,
        public readonly int $limit,
        public readonly int $resetAt,
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
            $retryAfter = max(0, $this->resetAt - time());
            $headers['Retry-After'] = (string) $retryAfter;
        }

        return $headers;
    }
}
