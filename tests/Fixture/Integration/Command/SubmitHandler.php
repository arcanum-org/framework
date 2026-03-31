<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Integration\Command;

final class SubmitHandler
{
    public function __invoke(Submit $command): void
    {
        // Command handler — void return means 204 No Content.
    }
}
