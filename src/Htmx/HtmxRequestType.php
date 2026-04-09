<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

/**
 * The type of htmx request, derived from the HX-Request-Type header.
 *
 * Full: htmx boosted navigation or hx-get with full page intent.
 * Partial: htmx partial swap targeting a specific element.
 */
enum HtmxRequestType: string
{
    case Full = 'full';
    case Partial = 'partial';
}
