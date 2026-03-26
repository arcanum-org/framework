<?php

declare(strict_types=1);

namespace Arcanum\Quill;

use Psr\Log\LoggerInterface;

/**
 * Quill Logger
 * -------------
 *
 * A Quill Logger is a PSR-3 compliant logger that sends logs to multiple
 * channels.
 */
class Logger implements LoggerInterface, ChannelLogger
{
    /**
     * The channels that this logger will send logs to.
     *
     * @var array<string, Channel>
     */
    private array $channels;

    /**
     * Create a new channel.
     *
     * We typehint the logger as a Monolog logger rather than a PSR-3 logger
     * becaue we want to be able to get the channel name from the logger which
     * is not a method defined by PSR-3.
     */
    public function __construct(
        Channel ...$channels,
    ) {
        $this->channels = [];
        foreach ($channels as $channel) {
            $this->channels[$channel->name] = $channel;
        }

        if (!isset($this->channels['default'])) {
            $this->channels['default'] = new Channel(
                new \Monolog\Logger('default')
            );
        }
    }

    /**
     * Add a channel to the logger.
     */
    public function addChannel(Channel $channel): void
    {
        $this->channels[$channel->name] = $channel;
    }

    /**
     * Get a channel by name.
     */
    public function channel(string $name): Channel
    {
        return $this->channels[$name] ?? throw new \InvalidArgumentException(
            "Channel $name does not exist."
        );
    }

    /**
     * System is unusable.
     */
    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->channels['default']->emergency($message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     */
    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->channels['default']->alert($message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     */
    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->channels['default']->critical($message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     */
    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->channels['default']->error($message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     */
    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->channels['default']->warning($message, $context);
    }

    /**
     * Normal but significant events.
     */
    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->channels['default']->notice($message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     */
    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->channels['default']->info($message, $context);
    }

    /**
     * Detailed debug information.
     */
    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->channels['default']->debug($message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param 'alert'|'critical'|'debug'|'emergency'|'error'|'info'|'notice'|'warning'|\Monolog\Level $level
     */
    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $this->channels['default']->log($level, $message, $context);
    }
}
