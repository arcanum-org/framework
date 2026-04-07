<?php

declare(strict_types=1);

namespace Arcanum\Glitch;

use Arcanum\Cabinet\Application;
use Arcanum\Quill\ChannelLogger;

class Handler implements ErrorHandler, ExceptionHandler, ShutdownHandler, Reporter
{
    /**
     * @var class-string[]
     */
    protected array $reporters = [];

    /**
     * Building the reporters is deferred until they are actually needed.
     *
     * @var callable(): Reporter[]
     */
    protected $buildReporters;

    /**
     * Create a new error handler.
     */
    public function __construct(private ChannelLogger $logger, Application $container)
    {
        $this->buildReporters = function () use ($container): array {
            $reporters = [];

            foreach ($this->reporters as $reporterName) {
                $reporter = $container->get($reporterName);
                if (!$reporter instanceof Reporter) {
                    throw new \RuntimeException("$reporterName must implement " . Reporter::class);
                }
                $reporters[] = $reporter;
            }

            return $reporters;
        };
    }

    /**
     * Register an exception reporter.
     *
     * @param class-string<Reporter> $reporter
     */
    public function registerReporter(string $reporter): void
    {
        $this->reporters[] = $reporter;
    }

    /**
     * Fan an exception out to every reporter that claims it.
     */
    private function report(\Throwable $e): void
    {
        $factory = $this->buildReporters;
        foreach ($factory() as $reporter) {
            if ($reporter->handles(\get_class($e))) {
                $reporter($e);
            }
        }
    }

    /**
     * Report an exception.
     */
    public function __invoke(\Throwable $e): void
    {
        $this->handleException($e);
    }

    /**
     * Check if this reporter handles the given exception.
     *
     * @param class-string<\Throwable> $exceptionName
     */
    public function handles(string $exceptionName): bool
    {
        // The default handler reports all exceptions.
        return true;
    }

    /**
     * Error handler.
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (Level::isDeprecation($errno)) {
            return $this->handleDeprecationError($errno, $errstr, $errfile, $errline);
        }
        if (\error_reporting() & $errno) {
            throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
        }

        // returning false will let the default error handler handle the error
        return false;
    }

    /**
     * Deprecation errors are logged to a separate channel.
     */
    private function handleDeprecationError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        try {
            $this->logger
                ->channel('deprecations')
                ->warning($errstr, compact('errno', 'errfile', 'errline'));
        } catch (\Throwable $e) {
            $this->handleException(new \ErrorException($errstr, 0, $errno, $errfile, $errline, $e));
        }

        // prevent the default error handler from handling the error
        return true;
    }

    /**
     * Shutdown handler.
     */
    public function handleShutdown(): void
    {
        $error = \error_get_last();

        if ($error !== null && Level::isFatal($error['type'])) {
            $this->handleException(new \RuntimeException(
                message: 'Exception occurred in shutdown handler',
                previous: new \ErrorException(
                    message: $error['message'],
                    code: 0,
                    severity: $error['type'],
                    filename: $error['file'],
                    line: $error['line']
                )
            ));
        }
    }

    /**
     * Exception handler.
     *
     * Logging is the floor: every exception is written to the default
     * log channel before reporters are notified. This guarantees errors
     * are never silently dropped, regardless of whether (or how) the
     * application has wired up its reporter chain. Reporters are then
     * fanned out as additional sinks (Sentry, Bugsnag, etc.).
     */
    public function handleException(\Throwable $ex): void
    {
        $this->logException($ex);

        try {
            $this->report($ex);
        } catch (\Throwable $e) {
            // if a reporter throws an exception, log that too so the
            // reporter failure itself is visible.
            $this->logException($e);
        }
    }

    /**
     * Log an exception.
     *
     * This method is used as a fallback when a reporter throws an exception.
     * You should typically register the LogReporter to log exceptions, as this is
     * just a fallback.
     */
    private function logException(\Throwable $ex): void
    {
        try {
            $this->logger->channel('default')->critical($ex->getMessage(), ['exception' => $ex]);
        } catch (\Throwable $e) {
            // if the logger throws an exception, we try to log it to
            // the default PHP logger
            \error_log($e->getMessage());
        }
    }
}
