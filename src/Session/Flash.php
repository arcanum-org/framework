<?php

declare(strict_types=1);

namespace Arcanum\Session;

/**
 * Write-once, read-once flash message bag.
 *
 * Flash data is written for the "next" request. When the session middleware
 * starts a new request, it promotes "next" data to "current" and clears
 * "next". Current data is available for reading during that request and
 * discarded at the end.
 */
final class Flash
{
    /** @var array<string, string> Messages available during this request. */
    private array $current = [];

    /** @var array<string, string> Messages queued for the next request. */
    private array $next = [];

    /**
     * Restore flash state from session data.
     *
     * @param array<string, string> $next Data saved from the previous request's "next" bucket.
     */
    public function __construct(array $next = [])
    {
        $this->current = $next;
    }

    /**
     * Queue a message for the next request.
     */
    public function set(string $key, string $message): void
    {
        $this->next[$key] = $message;
    }

    /**
     * Read a message from the current request's flash data.
     */
    public function get(string $key, string $default = ''): string
    {
        return $this->current[$key] ?? $default;
    }

    /**
     * Check whether a key exists in the current request's flash data.
     */
    public function has(string $key): bool
    {
        return isset($this->current[$key]);
    }

    /**
     * All current flash messages.
     *
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->current;
    }

    /**
     * Data to persist into the session for the next request.
     *
     * @return array<string, string>
     */
    public function pending(): array
    {
        return $this->next;
    }
}
