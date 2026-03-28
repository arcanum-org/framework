<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor\Fixture;

final class PostDoSomethingHandler
{
    public function __invoke(DoSomething $command): DoSomethingResult
    {
        return new DoSomethingResult('post:' . $command->name);
    }
}
