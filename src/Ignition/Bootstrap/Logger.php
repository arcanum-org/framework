<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Cabinet\Application;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Quill\Handler;
use Arcanum\Quill\Logger as QuillLogger;
use Arcanum\Quill\Channel;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Handler\HandlerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Bootstrap the Quill Logger
 *
 * @phpstan-type MonologLevel 100|200|250|300|400|500|550|600|'ALERT'|'alert'|'CRITICAL'|'critical'|'DEBUG'|'debug'|'EMERGENCY'|'emergency'|'ERROR'|'error'|'INFO'|'info'|'NOTICE'|'notice'|'WARNING'|'warning'|\Monolog\Level
 *
 * @phpstan-type StreamDef array{
 *   type: Handler::STREAM,
 *   level?: MonologLevel,
 *   path?: string,
 *   bubble?: bool,
 *   filePermission?: int|null,
 *   useLocking?: bool
 * }
 *
 * @phpstan-type RotatingFileDef array{
 *   type: Handler::ROTATING_FILE,
 *   level?: MonologLevel,
 *   path?: string,
 *   maxFiles?: int,
 *   bubble?: bool,
 *   filePermission?: int|null,
 *   useLocking?: bool
 * }
 *
 * @phpstan-type SyslogDef array{
 *   type: Handler::SYSLOG,
 *   level?: MonologLevel,
 *   facility?: string,
 *   bubble?: bool,
 *   ident?: string,
 *   logopts?: int
 * }
 *
 * @phpstan-type ErrorLogDef array{
 *   type: Handler::ERROR_LOG,
 *   level?: MonologLevel,
 *   bubble?: bool,
 *   sapi?: bool,
 *   expandNewlines?: bool
 * }
 *
 * @phpstan-type ProcessHandlerDef array{
 *   type: Handler::PROCESS,
 *   level?: MonologLevel,
 *   command?: string,
 *   bubble?: bool,
 *   cwd?: string|null
 * }
 *
 * @phpstan-type LoggerConfig array{
 *   handlers: array<string,StreamDef|RotatingFileDef|SyslogDef|ErrorLogDef|ProcessHandlerDef>,
 *   channels: array<string,string[]>
 * }
 */
class Logger implements Bootstrapper
{
    /**
     * Bootstrap the application.
     */
    public function bootstrap(Application $container): void
    {
        /** @var \Arcanum\Gather\Configuration $config */
        $config = $container->get(\Arcanum\Gather\Configuration::class);

        /** @var LoggerConfig $logConfig */
        $logConfig = $config->get('log');

        /**
         * Get the Kernel
         *
         * @var \Arcanum\Ignition\Kernel $kernel
         */
        $kernel = $container->get(\Arcanum\Ignition\Kernel::class);

        /**
         * Get the Kernel's files directory
         */
        $filesDirectory = $kernel->filesDirectory();

        $container->factory(QuillLogger::class, fn() => $this->makeQuillLogger((array)$logConfig, $filesDirectory));
    }

    /**
     * Make a Monolog Handler from the configuration
     *
     * @param StreamDef|RotatingFileDef|SyslogDef|ErrorLogDef|ProcessHandlerDef $handlerConfig
     */
    private function makeHandler(string $handlerName, array $handlerConfig, string $filesDirectory): HandlerInterface
    {
        $ds = DIRECTORY_SEPARATOR;

        return match ($handlerConfig['type']) {
            Handler::STREAM => new \Monolog\Handler\StreamHandler(
                $filesDirectory . $ds . ($handlerConfig['path'] ?? 'logs' . $ds . $handlerName . '.log'),
                $handlerConfig['level'] ?? LogLevel::INFO,
                $handlerConfig['bubble'] ?? true,
                $handlerConfig['filePermission'] ?? null,
                $handlerConfig['useLocking'] ?? false,
            ),
            Handler::ROTATING_FILE => new \Monolog\Handler\RotatingFileHandler(
                $filesDirectory . $ds . ($handlerConfig['path'] ?? 'logs' . $ds . $handlerName . '.log'),
                $handlerConfig['maxFiles'] ?? 30,
                $handlerConfig['level'] ?? LogLevel::DEBUG,
                $handlerConfig['bubble'] ?? true,
                $handlerConfig['filePermission'] ?? null,
                $handlerConfig['useLocking'] ?? false,
            ),
            Handler::SYSLOG => new \Monolog\Handler\SyslogHandler(
                $handlerConfig['ident'] ?? 'arcanum',
                $handlerConfig['facility'] ?? \LOG_USER,
                $handlerConfig['level'] ?? LogLevel::INFO,
                $handlerConfig['bubble'] ?? true,
                $handlerConfig['logopts'] ?? \LOG_PID,
            ),
            Handler::ERROR_LOG => new ErrorLogHandler(
                empty($handlerConfig['sapi']) ? ErrorLogHandler::OPERATING_SYSTEM : ErrorLogHandler::SAPI,
                $handlerConfig['level'] ?? LogLevel::ERROR,
                $handlerConfig['bubble'] ?? true,
                $handlerConfig['expandNewlines'] ?? false,
            ),
            Handler::PROCESS => new \Monolog\Handler\ProcessHandler(
                $handlerConfig['command'] ?? 'cat',
                $handlerConfig['level'] ?? LogLevel::DEBUG,
                $handlerConfig['bubble'] ?? true,
                $handlerConfig['cwd'] ?? null,
            ),
        };
    }

    /**
     * Make a Quill channel from the configuration
     *
     * @param string[] $handlerNames
     * @param array{string,HandlerInterface} $handlers
     */
    private function makeChannel(string $name, array $handlerNames, array $handlers): Channel
    {
        $monologger = new \Monolog\Logger($name);
        foreach ($handlerNames as $handlerName) {
            $monologger->pushHandler($handlers[$handlerName]);
        }
        return new Channel($monologger);
    }

    /**
     * Make a QuillLogger from the configuration
     *
     * @param array{
     *   handlers: array<string,StreamDef|RotatingFileDef|SyslogDef|ErrorLogDef|ProcessHandlerDef>,
     *   channels: array<string,string[]>
     * } $logConfig
     */
    private function makeQuillLogger(array $logConfig, string $filesDirectory): LoggerInterface
    {
        /** @var array{string,HandlerInterface} $handlers */
        $handlers = [];

        // Build the configured handlers
        foreach ($logConfig['handlers'] as $handlerName => $handlerConfig) {
            $handlers[$handlerName] = $this->makeHandler($handlerName, $handlerConfig, $filesDirectory);
        }

        // Build the configured channels
        $channels = [];
        foreach ($logConfig['channels'] as $channelName => $channelHandlers) {
            /** @var array{string,HandlerInterface} $handlers */
            $channels[] = $this->makeChannel($channelName, $channelHandlers, $handlers);
        }

        return new QuillLogger(...$channels);
    }
}
