<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor\Fixture;

final class LegacyActionHandler
{
    /** @phpstan-ignore missingType.return */
    public function __invoke(LegacyAction $command)
    {
        // no return type declaration — treated as void
    }
}
