<?php

declare(strict_types=1);

namespace Arcanum\Validation;

/**
 * Marker interface for Conveyor middleware that validates DTO input.
 *
 * Implement this on any custom validation middleware so the bus knows
 * validation is covered. Without a ValidatesInput middleware registered,
 * the bus warns (or throws in debug) when a DTO has validation attributes.
 */
interface ValidatesInput
{
}
