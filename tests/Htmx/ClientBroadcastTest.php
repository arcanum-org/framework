<?php

declare(strict_types=1);

namespace Arcanum\Test\Htmx;

use Arcanum\Htmx\BroadcastAfterSettle;
use Arcanum\Htmx\BroadcastAfterSwap;
use Arcanum\Htmx\ClientBroadcast;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ClientBroadcast::class)]
#[CoversClass(BroadcastAfterSwap::class)]
#[CoversClass(BroadcastAfterSettle::class)]
final class ClientBroadcastTest extends TestCase
{
    public function testImmediateBroadcastIsClientBroadcastOnly(): void
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

    public function testAfterSwapBroadcastIsClientBroadcastAndAfterSwap(): void
    {
        // Arrange
        $event = new class implements BroadcastAfterSwap {
            public function eventName(): string
            {
                return 'list-refreshed';
            }

            public function payload(): array
            {
                return [];
            }
        };

        // Act & Assert
        $this->assertInstanceOf(ClientBroadcast::class, $event);
        $this->assertInstanceOf(BroadcastAfterSwap::class, $event);
        $this->assertSame('list-refreshed', $event->eventName());
    }

    public function testAfterSettleBroadcastIsClientBroadcastAndAfterSettle(): void
    {
        // Arrange
        $event = new class implements BroadcastAfterSettle {
            public function eventName(): string
            {
                return 'animation-ready';
            }

            public function payload(): array
            {
                return ['target' => '#panel'];
            }
        };

        // Act & Assert
        $this->assertInstanceOf(ClientBroadcast::class, $event);
        $this->assertInstanceOf(BroadcastAfterSettle::class, $event);
        $this->assertSame('animation-ready', $event->eventName());
        $this->assertSame(['target' => '#panel'], $event->payload());
    }
}
