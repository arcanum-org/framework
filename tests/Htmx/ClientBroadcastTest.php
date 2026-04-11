<?php

declare(strict_types=1);

namespace Arcanum\Test\Htmx;

use Arcanum\Htmx\ClientBroadcast;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClientBroadcast::class)]
final class ClientBroadcastTest extends TestCase
{
    public function testBroadcastEventNameAndPayload(): void
    {
        // Arrange
        $event = new class implements ClientBroadcast {
            public function eventName(): string
            {
                return 'cart-updated';
            }

            public function payload(): array
            {
                return ['count' => 3];
            }
        };

        // Act & Assert
        $this->assertInstanceOf(ClientBroadcast::class, $event);
        $this->assertSame('cart-updated', $event->eventName());
        $this->assertSame(['count' => 3], $event->payload());
    }
}
