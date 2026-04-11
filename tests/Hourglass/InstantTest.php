<?php

declare(strict_types=1);

namespace Arcanum\Test\Hourglass;

use Arcanum\Hourglass\Instant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Instant::class)]
final class InstantTest extends TestCase
{
    public function testInstantCarriesLabelAndTime(): void
    {
        $instant = new Instant('arcanum.start', 1712534400.123456);

        $this->assertSame('arcanum.start', $instant->label);
        $this->assertSame(1712534400.123456, $instant->time);
    }
}
