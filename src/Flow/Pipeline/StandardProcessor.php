<?php

declare(strict_types=1);

namespace Arcanum\Flow\Pipeline;

use Arcanum\Flow\Interrupted;

class StandardProcessor implements Processor
{
    /**
     * Process a payload through a series of stages.
     *
     * @param callable(object): (object|null) $stages
     */
    public function process(object $payload, callable ...$stages): object
    {
        // $stages = array_reverse($stages);
        foreach ($stages as $stage) {
            $payload = $stage($payload);
            if ($payload === null) {
                throw new Interrupted("Stage did not return a payload.");
            }
        }
        return $payload;
    }
}
