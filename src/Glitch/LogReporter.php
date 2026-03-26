<?php

declare(strict_types=1);

namespace Arcanum\Glitch;

use Arcanum\Quill\ChannelLogger;

class LogReporter implements Reporter
{
    /**
     * @param array<
     *   class-string<\Throwable>,
     *   'alert'|'critical'|'debug'|'emergency'|'error'|'info'|'notice'|'warning'
     * > $levels
     * @param array<class-string<\Throwable>,string> $channels
     */
    public function __construct(
        private ChannelLogger $logger,
        private array $levels = [ \Throwable::class => 'error' ],
        private array $channels = [ \Throwable::class => 'default' ],
    ) {
    }

    /**
     * Report an exception.
     */
    public function __invoke(\Throwable $e): void
    {
        $level = $this->getLevel($e);
        $channel = $this->getChannel($e);

        $this->logger->channel($channel)->$level($e->getMessage(), [ 'exception' => $e ]);
    }

    /**
     * Check if this reporter handles the given exception.
     */
    public function handles(string $exceptionName): bool
    {
        // LogReporter handles all exceptions.
        return true;
    }

    /**
     * Get the log level for the given exception.
     */
    private function getLevel(\Throwable $e): string
    {
        foreach ($this->levels as $exception => $l) {
            if ($e instanceof $exception) {
                return $l;
            }
        }

        return 'error';
    }

    /**
     * Get the log channel for the given exception.
     */
    private function getChannel(\Throwable $e): string
    {
        foreach ($this->channels as $exception => $c) {
            if ($e instanceof $exception) {
                return $c;
            }
        }

        return 'default';
    }
}
