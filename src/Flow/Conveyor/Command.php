<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor;

/**
 * A dynamic Command DTO for handlers that don't define an explicit DTO class.
 *
 * When only a handler exists (e.g., MakePaymentHandler without MakePayment),
 * the framework creates a Command from the request body and dispatches it
 * to the handler. Data is accessed via Gather's typed accessors.
 *
 * ```php
 * class MakePaymentHandler {
 *     public function __invoke(Command $command): void {
 *         $amount = $command->asFloat('amount');
 *         $currency = $command->asString('currency');
 *     }
 * }
 * ```
 */
final class Command extends DynamicDTO
{
}
