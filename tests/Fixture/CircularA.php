<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture;

class CircularA
{
    public function __construct(public CircularB $b)
    {
    }
}
