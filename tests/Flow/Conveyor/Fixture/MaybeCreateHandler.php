<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor\Fixture;

final class MaybeCreateHandler
{
    /** @phpstan-ignore return.unusedType */
    public function __invoke(MaybeCreate $command): ?DoSomethingResult
    {
        return new DoSomethingResult($command->name);
    }
}
