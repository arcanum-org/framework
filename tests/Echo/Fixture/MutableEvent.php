<?php

declare(strict_types=1);

namespace Arcanum\Test\Echo\Fixture;

use Arcanum\Echo\Event;

class MutableEvent extends Event
{
    public int $counter = 0;
}
