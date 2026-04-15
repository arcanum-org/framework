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
    private static Application|null $container = null;

    public function bootstrap(Application $container): void
    {
        self::$reservedMemory = str_repeat('A', 32768);
        self::$container = $container;

        // Register default Glitch handlers when no app-specific ones exist.
        // Same has() guard pattern as Bootstrap\Logger — apps override by
        // registering their own implementation before or after bootstrap.
        if (!$container->has(ExceptionHandler::class)) {
            $container->service(ExceptionHandler::class, \Arcanum\Glitch\Handler::class);
        }
        if (!$container->has(ErrorHandler::class)) {
            $container->service(ErrorHandler::class, \Arcanum\Glitch\Handler::class);
        }
        if (!$container->has(ShutdownHandler::class)) {
            $container->service(ShutdownHandler::class, \Arcanum\Glitch\Handler::class);
        }

        // Report all PHP errors.
        \error_reporting(-1);

        // Set the error handler.
        \set_error_handler([$this, 'handleError']);

        // Set the exception handler.
        \set_exception_handler([$this, 'handleException']);

        // Set the shutdown handler.
        \register_shutdown_function([$this, 'handleShutdown']);

        /** @var \Arcanum\Gather\Configuration $config */
        $config = $container->get(\Arcanum\Gather\Configuration::class);

        // Resolve verbose_errors: defaults to app.debug when not explicitly set.
        if (!$config->has('app.verbose_errors')) {
            $debug = $config->get('app.debug');
            $config->set('app.verbose_errors', $debug === true || $debug === 'true');
        }

        // Disable PHP's display_errors setting if we are not in a testing environment.
        if ($config->asString('app.environment') !== 'testing') {
            \ini_set('display_errors', 'Off');
        }
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        try {
            if (self::$container === null || !self::$container->has(ErrorHandler::class)) {
                return false;
            }

            /** @var ErrorHandler $handler */
            $handler = self::$container->get(ErrorHandler::class);

            return $handler->handleError($errno, $errstr, $errfile, $errline);
        } catch (\Throwable $ex) {
            $this->handleException($ex);
            return true;
        }
    }

    public function handleException(Throwable $ex): void
    {
        self::$reservedMemory = null;

        try {
            if (self::$container === null || !self::$container->has(ExceptionHandler::class)) {
                \error_log($ex->getMessage());
                return;
            }

            /** @var ExceptionHandler $handler */
            $handler = self::$container->get(ExceptionHandler::class);
            $handler->handleException($ex);
        } catch (Throwable $e) {
            \error_log($e->getMessage());
        }
    }

    public function handleShutdown(): void
    {
        self::$reservedMemory = null;

        try {
            if (self::$container === null || !self::$container->has(ShutdownHandler::class)) {
                return;
            }

            /** @var ShutdownHandler $handler */
            $handler = self::$container->get(ShutdownHandler::class);
            $handler->handleShutdown();
        } catch (\Throwable) {
            // Shutdown handlers must never throw — swallow to prevent
            // cascading failures during process teardown.
        }
    }
}
