# Arcanum Glitch

Glitch is the error and exception handling package. It catches PHP errors, uncaught exceptions, and fatal shutdown errors, then routes them through a reporter system that can log, alert, or render them as HTTP responses. Three layers of fallbacks ensure errors are never silently swallowed.

## How it works

`Handler` is the central class. It implements all three PHP error hooks and coordinates reporting:

```php
$handler = new Handler($logger, $container);

// Register reporters — called when exceptions occur
$handler->registerReporter(LogReporter::class);
$handler->registerReporter(SlackAlertReporter::class);

// PHP hooks (normally set by the Exceptions bootstrapper)
set_error_handler([$handler, 'handleError']);
set_exception_handler([$handler, 'handleException']);
register_shutdown_function([$handler, 'handleShutdown']);
```

When an exception reaches the handler:

1. Each registered reporter that `handles()` the exception type is called
2. If a reporter throws, the handler falls back to the logger
3. If the logger throws, the handler falls back to `error_log()`

Errors never disappear — even if your reporting infrastructure is broken.

## Error classification

PHP errors are classified by the `Level` enum and handled differently:

| Error type | Behavior |
|---|---|
| **Deprecations** (`E_DEPRECATED`, `E_USER_DEPRECATED`) | Logged to the `deprecations` channel at warning level. Never thrown. |
| **Reported errors** (in `error_reporting()` mask) | Converted to `ErrorException` and thrown — becomes an exception. |
| **Non-reported errors** | Passed to PHP's default handler (returns `false`). |
| **Fatal errors** (at shutdown) | Caught by `handleShutdown()`, wrapped in `ErrorException`, and sent through `handleException()`. |

```php
Level::isDeprecation(E_DEPRECATED);   // true
Level::isFatal(E_COMPILE_ERROR);      // true
Level::isFatal(E_WARNING);            // false
```

The enum accepts both `Level` cases and raw `int` values for interop with PHP's error constants.

## Reporters

Reporters are plugins that handle exceptions. Each reporter declares which exception types it handles:

```php
interface Reporter
{
    public function __invoke(\Throwable $e): void;
    public function handles(string $exceptionName): bool;
}
```

### LogReporter

The built-in reporter. Logs exceptions with configurable log levels and channels per exception type:

```php
$reporter = new LogReporter(
    logger: $channelLogger,
    levels: [
        \RuntimeException::class => 'critical',
        \InvalidArgumentException::class => 'warning',
        \Throwable::class => 'error',  // fallback
    ],
    channels: [
        \App\Domain\PaymentException::class => 'payments',
        \Throwable::class => 'default',  // fallback
    ],
);
```

Exception inheritance is respected — a `PaymentException` that extends `RuntimeException` matches both the `RuntimeException` level and the `PaymentException` channel.

### Custom reporters

Implement the `Reporter` interface to send exceptions to Slack, Sentry, PagerDuty, or anywhere else:

```php
class SlackAlertReporter implements Reporter
{
    public function __invoke(\Throwable $e): void
    {
        // send to Slack webhook
    }

    public function handles(string $exceptionName): bool
    {
        return is_a($exceptionName, CriticalException::class, true);
    }
}
```

Reporters are resolved lazily from the container — they're registered by class name and only instantiated when an exception occurs.

## HTTP exceptions

`HttpException` carries an HTTP status code for exceptions that should map to specific HTTP responses:

```php
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;

throw new HttpException(StatusCode::NotFound);
// → 404, message: "Not Found"

throw new HttpException(StatusCode::Forbidden, 'Access denied');
// → 403, message: "Access denied"
```

When no message is provided, the status code's reason phrase is used automatically. The `ExceptionRenderer` interface converts these into proper HTTP responses — see [Shodo's JsonExceptionRenderer](../Shodo/README.md) for the built-in implementation.

## SafeCall

Wraps PHP built-in function calls to capture warnings instead of suppressing them with `@`:

```php
use Arcanum\Glitch\SafeCall;

$contents = SafeCall::call('file_get_contents', '/nonexistent');
$error = SafeCall::lastError();
// → "file_get_contents(/nonexistent): Failed to open stream: No such file or directory"
```

This is safer than `@file_get_contents()` because the error is captured, not silenced. The temporary error handler is always restored in a `finally` block, even if the function throws.

## The interfaces

- **ErrorHandler** — `handleError(int $errno, string $errstr, string $errfile, int $errline): bool`
- **ExceptionHandler** — `handleException(\Throwable $ex): void`
- **ShutdownHandler** — `handleShutdown(): void`
- **Reporter** — `__invoke(\Throwable $e): void` + `handles(string $exceptionName): bool`
- **ExceptionRenderer** — `render(\Throwable $e): ResponseInterface`

## At a glance

```
Handler (implements ErrorHandler, ExceptionHandler, ShutdownHandler, Reporter)
|-- handleError()     — deprecation logging or ErrorException conversion
|-- handleException() — dispatch to reporters with triple fallback
|-- handleShutdown()  — catch fatal errors at script termination
\-- registerReporter() — add reporter class names (lazy-loaded)

Level (enum)
|-- isDeprecation() — E_DEPRECATED, E_USER_DEPRECATED
\-- isFatal()       — E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_PARSE

LogReporter — configurable per-exception-type log levels and channels
SafeCall    — capture warnings from built-in functions
HttpException — exception with HTTP status code
ExceptionRenderer — convert exceptions to HTTP responses
```
