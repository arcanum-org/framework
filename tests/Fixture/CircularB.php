<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture;

class CircularB
{
    public function __construct(public CircularA $a)
    {
    }
}
