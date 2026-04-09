<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

/**
 * Timing variant of ClientBroadcast — the event fires after the settle
 * step is complete, routed to the HX-Trigger-After-Settle response header.
 */
interface BroadcastAfterSettle extends ClientBroadcast
{
}
