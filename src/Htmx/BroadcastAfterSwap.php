<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

/**
 * Timing variant of ClientBroadcast — the event fires after the swap
 * is complete, routed to the HX-Trigger-After-Swap response header.
 */
interface BroadcastAfterSwap extends ClientBroadcast
{
}
