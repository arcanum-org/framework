<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Ignition\Bootstrapper;
use Arcanum\Cabinet\Application;
use Arcanum\Glitch\ErrorHandler;
use Arcanum\Glitch\ExceptionHandler;
use Arcanum\Glitch\ShutdownHandler;
use Throwable;

/**
 * Bootstrap the exception handler
 */
class Exceptions implements Bootstrapper, ErrorHandler, ExceptionHandler, ShutdownHandler
{
    /**
     * In order to properly display out-of-memory errors, we need to reserve
     * some memory.
     */
    public static string|null $reservedMemory = null;

    /**
     * A reference to the container for use in the exception handler.
     */
    private static Application|null $container;

    public function bootstrap(Application $container): void
    {
        self::$reservedMemory = str_repeat('A', 32768);
        self::$container = $container;

        // Report all PHP errors.
        \error_reporting(-1);

        // Set the error handler.
        \set_error_handler([$this, 'handleError']);

        // Set the exception handler.
        \set_exception_handler([$this, 'handleException']);

        // Set the shutdown handler.
        \register_shutdown_function([$this, 'handleShutdown']);

        // Disable PHP's display_errors setting if we are not in a testing environment.

        /** @var \Arcanum\Gather\Configuration $config */
        $config = $container->get(\Arcanum\Gather\Configuration::class);
        if ($config->asString('app.environment') !== 'testing') {
            \ini_set('display_errors', 'Off');
        }
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        try {
            /** @var ErrorHandler|null $handler */
            $handler = self::$container?->get(ErrorHandler::class);

            if ($handler === null) {
                // if there is no error handler, we let the default error handler handle the error
                return false;
            }

            return $handler->handleError($errno, $errstr, $errfile, $errline);
        } catch (\Throwable $ex) {
            $this->handleException($ex);
            return true;
        }
    }

    public function handleException(Throwable $ex): void
    {
        // Free the reserved memory.
        self::$reservedMemory = null;

        try {
            /** @var ExceptionHandler|null $handler */
            $handler = self::$container?->get(ExceptionHandler::class);

            if ($handler === null) {
                // if there is no exception handler, we at least try to log the exception to
                // the default PHP logger
                \error_log($ex->getMessage());
                return;
            }

            $handler->handleException($ex);
        } catch (Throwable $e) {
            // if the exception handler throws an exception, we at least try to log it to
            // the default PHP logger
            \error_log($e->getMessage());
        }
    }

    public function handleShutdown(): void
    {
        // Free the reserved memory.
        self::$reservedMemory = null;

        /** @var ShutdownHandler|null $handler */
        $handler = self::$container?->get(ShutdownHandler::class);

        if ($handler === null) {
            return;
        }

        $handler->handleShutdown();
    }
}
