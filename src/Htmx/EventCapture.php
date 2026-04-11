<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Decorator around Echo's dispatcher that records ClientBroadcast events.
 *
 * The HtmxEventTriggerMiddleware installs this before the handler runs
 * and reads the captured events after the handler returns, merging them
 * into the appropriate HX-Trigger response headers.
 *
 * Non-ClientBroadcast events pass through without recording.
 */
final class EventCapture implements EventDispatcherInterface
{
    /** @var list<ClientBroadcast> */
    private array $captured = [];

    public function __construct(
        private readonly EventDispatcherInterface $inner,
    ) {
    }

    public function dispatch(object $event): object
    {
        $result = $this->inner->dispatch($event);

        if ($event instanceof ClientBroadcast) {
            $this->captured[] = $event;
        }

        return $result;
    }

    /**
     * Return all captured ClientBroadcast events and clear the buffer.
     *
     * @return list<ClientBroadcast>
     */
    public function drain(): array
    {
        $events = $this->captured;
        $this->captured = [];
        return $events;
    }
}
