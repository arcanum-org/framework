<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor\Fixture;

final class FireAndForgetHandler
{
    public function __invoke(FireAndForget $command): void
    {
        // void handler — no return
    }
}
