<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

/**
 * Marker interface for domain events that should project as
 * HX-Trigger server-to-client signals on htmx responses.
 *
 * Implement this on any Echo event class that should notify the
 * browser after a command completes. The HtmxEventTriggerMiddleware
 * collects all ClientBroadcast events fired during the request and
 * merges them into the appropriate HX-Trigger response header.
 *
 * For timing control, implement BroadcastAfterSwap or
 * BroadcastAfterSettle instead — they route to HX-Trigger-After-Swap
 * and HX-Trigger-After-Settle respectively.
 */
interface ClientBroadcast
{
    /**
     * The event name sent to the browser via HX-Trigger.
     *
     * This becomes the htmx event that client-side elements can
     * listen for with hx-trigger="eventName from:body".
     */
    public function eventName(): string;

    /**
     * Optional payload data included with the event.
     *
     * Serialized as JSON in the HX-Trigger header value.
     * Return an empty array for a signal-only event.
     *
     * @return array<string, mixed>
     */
    public function payload(): array;
}
