<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor\Fixture;

final class DoSomething
{
    public function __construct(public readonly string $name)
    {
    }
}
