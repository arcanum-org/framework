<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor\Fixture;

final class QueueJobHandler
{
    /** @phpstan-ignore return.unusedType */
    public function __invoke(QueueJob $command): ?DoSomethingResult
    {
        return null;
    }
}
