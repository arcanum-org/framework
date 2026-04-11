<?php

declare(strict_types=1);

namespace Arcanum\Test\Htmx;

use Arcanum\Htmx\ClientBroadcast;
use Arcanum\Htmx\EventCapture;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\EventDispatcher\EventDispatcherInterface;

#[CoversClass(EventCapture::class)]
final class EventCaptureTest extends TestCase
{
    public function testDelegatesDispatchToInner(): void
    {
        // Arrange
        $event = new \stdClass();
        $inner = $this->createMock(EventDispatcherInterface::class);
        $inner->expects($this->once())
            ->method('dispatch')
            ->with($event)
            ->willReturn($event);

        $capture = new EventCapture($inner);

        // Act
        $result = $capture->dispatch($event);

        // Assert
        $this->assertSame($event, $result);
    }

    public function testCapturesClientBroadcastEvents(): void
    {
        // Arrange
        $inner = $this->createStub(EventDispatcherInterface::class);
        $inner->method('dispatch')->willReturnArgument(0);

        $broadcast = new class implements ClientBroadcast {
            public function eventName(): string
            {
                return 'item-added';
            }

            public function payload(): array
            {
                return ['id' => 1];
            }
        };

        $capture = new EventCapture($inner);

        // Act
        $capture->dispatch($broadcast);
        $events = $capture->drain();

        // Assert
        $this->assertCount(1, $events);
        $this->assertSame('item-added', $events[0]->eventName());
    }

    public function testIgnoresNonBroadcastEvents(): void
    {
        // Arrange
        $inner = $this->createStub(EventDispatcherInterface::class);
        $inner->method('dispatch')->willReturnArgument(0);

        $capture = new EventCapture($inner);

        // Act
        $capture->dispatch(new \stdClass());
        $events = $capture->drain();

        // Assert
        $this->assertSame([], $events);
    }

    public function testDrainClearsBuffer(): void
    {
        // Arrange
        $inner = $this->createStub(EventDispatcherInterface::class);
        $inner->method('dispatch')->willReturnArgument(0);

        $broadcast = new class implements ClientBroadcast {
            public function eventName(): string
            {
                return 'test';
            }

            public function payload(): array
            {
                return [];
            }
        };

        $capture = new EventCapture($inner);
        $capture->dispatch($broadcast);

        // Act
        $first = $capture->drain();
        $second = $capture->drain();

        // Assert
        $this->assertCount(1, $first);
        $this->assertSame([], $second);
    }

    public function testCapturesMultipleEventsInOrder(): void
    {
        // Arrange
        $inner = $this->createStub(EventDispatcherInterface::class);
        $inner->method('dispatch')->willReturnArgument(0);

        $eventA = new class implements ClientBroadcast {
            public function eventName(): string
            {
                return 'first';
            }

            public function payload(): array
            {
                return [];
            }
        };

        $eventB = new class implements ClientBroadcast {
            public function eventName(): string
            {
                return 'second';
            }

            public function payload(): array
            {
                return [];
            }
        };

        $capture = new EventCapture($inner);

        // Act
        $capture->dispatch($eventA);
        $capture->dispatch($eventB);
        $events = $capture->drain();

        // Assert
        $this->assertCount(2, $events);
        $this->assertSame('first', $events[0]->eventName());
        $this->assertSame('second', $events[1]->eventName());
        $this->assertInstanceOf(ClientBroadcast::class, $events[1]);
    }
}
