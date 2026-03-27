<?php

declare(strict_types=1);

namespace Arcanum\Glitch;

/**
 * SafeCall wraps PHP built-in function calls to capture warnings and errors
 * that would otherwise be emitted as PHP notices/warnings.
 *
 * Instead of suppressing errors with @, SafeCall installs a temporary error
 * handler that captures the error message, making it available for proper
 * exception handling.
 */
class SafeCall
{
    private static string|null $lastError = null;

    /**
     * Call a built-in function, capturing any PHP warning/notice it emits.
     *
     * @param callable-string $func
     */
    public static function call(string $func, mixed ...$args): mixed
    {
        self::$lastError = null;
        set_error_handler(self::handleError(...));

        try {
            return $func(...$args);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * Get the last error message captured by call(), or null if none.
     */
    public static function lastError(): string|null
    {
        return self::$lastError;
    }

    /**
     * Error handler that captures the error message.
     *
     * @internal
     */
    public static function handleError(int $type, string $message): bool
    {
        self::$lastError = $message;

        return true;
    }
}
