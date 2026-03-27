<?php

declare(strict_types=1);

namespace Arcanum\Test\Echo;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\Arcanum\Echo\Event::class)]
final class EventTest extends TestCase
{
    public function testStopPropagation(): void
    {
        // Arrange
        $event = new class extends \Arcanum\Echo\Event {
        };

        // Act
        $event->stopPropagation();

        // Assert
        $this->assertTrue($event->isPropagationStopped());
    }
}
