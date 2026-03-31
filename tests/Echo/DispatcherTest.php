<?php

declare(strict_types=1);

namespace Arcanum\Test\Echo;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Arcanum\Echo\Dispatcher;
use Arcanum\Echo\Provider;
use Arcanum\Echo\Event;
use Arcanum\Echo\UnknownEvent;
use Arcanum\Test\Echo\Fixture\SomethingHappened;
use Arcanum\Test\Echo\Fixture\ChildTriggered;
use Arcanum\Test\Echo\Fixture\MutableEvent;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Dispatcher::class)]
#[UsesClass(\Arcanum\Echo\UnknownEvent::class)]
#[UsesClass(\Arcanum\Echo\Provider::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\Pipeline::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\StandardProcessor::class)]
#[UsesClass(\Arcanum\Flow\Interrupted::class)]
#[UsesClass(Event::class)]
final class DispatcherTest extends TestCase
{
    public function testDispatch(): void
    {
        // Arrange

        /** @var Provider&\PHPUnit\Framework\MockObject\MockObject */
        $provider = $this->getMockBuilder(Provider::class)
            ->onlyMethods(['getListenersForEvent'])
            ->getMock();

        $provider
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->willReturn([]);

        /** @var Event&\PHPUnit\Framework\MockObject\MockObject */
        $event = $this->getMockBuilder(\Arcanum\Echo\Event::class)
            ->onlyMethods(['stopPropagation', 'isPropagationStopped'])
            ->getMock();

        $event
            ->expects($this->never())
            ->method('stopPropagation');

        $event
            ->expects($this->never())
            ->method('isPropagationStopped');

        $dispatcher = new \Arcanum\Echo\Dispatcher($provider);

        // Act
        $result = $dispatcher->dispatch($event);

        // Assert
        $this->assertSame($event, $result);
    }

    public function testDispatchUnknownObject(): void
    {
        // Arrange

        /** @var Provider&\PHPUnit\Framework\MockObject\MockObject */
        $provider = $this->getMockBuilder(Provider::class)
            ->onlyMethods(['getListenersForEvent'])
            ->getMock();

        $provider
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->willReturn([]);

        $event = new \stdClass();
        $dispatcher = new \Arcanum\Echo\Dispatcher($provider);

        // Act
        $result = $dispatcher->dispatch($event);

        // Assert
        $this->assertSame($event, $result);
    }

    public function testDispatchWithListeners(): void
    {
        // Arrange
        $count = 0;

        /** @var Provider&\PHPUnit\Framework\MockObject\MockObject */
        $provider = $this->getMockBuilder(Provider::class)
            ->onlyMethods(['getListenersForEvent'])
            ->getMock();

        $provider
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->willReturn([
                function (Event $event) use (&$count): Event {
                    $count++;
                    return $event;
                },
                function (Event $event) use (&$count): Event {
                    $count++;
                    return $event;
                },
                function (Event $event) use (&$count): Event {
                    $count++;
                    return $event;
                },
            ]);

        /** @var Event&\PHPUnit\Framework\MockObject\MockObject */
        $event = $this->getMockBuilder(\Arcanum\Echo\Event::class)
            ->onlyMethods(['stopPropagation', 'isPropagationStopped'])
            ->getMock();

        $event
            ->expects($this->never())
            ->method('stopPropagation');

        $event
            ->expects($this->exactly(3))
            ->method('isPropagationStopped')
            ->willReturn(false);

        $dispatcher = new \Arcanum\Echo\Dispatcher($provider);

        // Act
        $result = $dispatcher->dispatch($event);

        // Assert
        $this->assertSame($event, $result);
        $this->assertSame(3, $count);
    }

    public function testDispatchWithListenersAndStopPropagation(): void
    {
        // Arrange
        $count = 0;
        $listener = function (Event $event) use (&$count): Event {
            $count++;
            return $event;
        };
        $provider = new Provider();
        $provider->listen(Event::class, $listener);
        $provider->listen(Event::class, function (Event $event) use (&$count): Event {
            $count++;
            $event->stopPropagation();
            return $event;
        });
        $provider->listen(Event::class, function (Event $event): Event {
            $this->fail('Listener called after propagation was stopped.');
        });

        $event = new SomethingHappened();

        $dispatcher = new \Arcanum\Echo\Dispatcher($provider);

        // Act
        $result = $dispatcher->dispatch($event);

        // Assert
        $this->assertSame($event, $result);
        $this->assertSame(2, $count);
    }

    public function testIfSameListenerIsRegisteredForTwoEventsInInheritanceChainItIsOnlyDispatchedToOnce(): void
    {
        // Arrange
        $count = 0;
        $listener = function (Event $event) use (&$count): Event {
            $count++;
            return $event;
        };

        /** @var Provider&\PHPUnit\Framework\MockObject\MockObject */
        $provider = $this->getMockBuilder(Provider::class)
            ->onlyMethods(['getListenersForEvent'])
            ->getMock();

        $provider
            ->expects($this->once())
            ->method('getListenersForEvent')
            ->willReturn([
                $listener,
                $listener,
            ]);

        /** @var Event&\PHPUnit\Framework\MockObject\MockObject */
        $event = $this->getMockBuilder(\Arcanum\Echo\Event::class)
            ->onlyMethods(['stopPropagation', 'isPropagationStopped'])
            ->getMock();

        $event
            ->expects($this->never())
            ->method('stopPropagation');

        $event
            ->expects($this->once())
            ->method('isPropagationStopped')
            ->willReturn(false);

        $dispatcher = new \Arcanum\Echo\Dispatcher($provider);

        // Act
        $result = $dispatcher->dispatch($event);

        // Assert
        $this->assertSame($event, $result);
        $this->assertSame(1, $count);
    }

    public function testNonInterruptedExceptionPropagatesFromListener(): void
    {
        // Arrange
        $provider = new Provider();
        $provider->listen(SomethingHappened::class, function (Event $event): Event {
            throw new \RuntimeException('Listener failed');
        });

        $event = new SomethingHappened();
        $dispatcher = new Dispatcher($provider);

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Listener failed');

        // Act
        $dispatcher->dispatch($event);
    }

    public function testInterruptedExceptionStopsPropagationAndReturnsEvent(): void
    {
        // Arrange
        $count = 0;
        $provider = new Provider();
        $provider->listen(SomethingHappened::class, function (Event $event) use (&$count): Event {
            $count++;
            $event->stopPropagation();
            return $event;
        });
        $provider->listen(SomethingHappened::class, function (Event $event): Event {
            $this->fail('Listener called after Interrupted');
        });

        $event = new SomethingHappened();
        $dispatcher = new Dispatcher($provider);

        // Act
        $result = $dispatcher->dispatch($event);

        // Assert
        $this->assertSame($event, $result);
        $this->assertSame(1, $count);
    }

    public function testEventMutationPropagatesThroughListenerChain(): void
    {
        // Arrange
        $provider = new Provider();
        $provider->listen(MutableEvent::class, function (MutableEvent $event): MutableEvent {
            $event->counter += 10;
            return $event;
        });
        $provider->listen(MutableEvent::class, function (MutableEvent $event): MutableEvent {
            $event->counter *= 3;
            return $event;
        });
        $provider->listen(MutableEvent::class, function (MutableEvent $event): MutableEvent {
            $event->counter += 5;
            return $event;
        });

        $event = new MutableEvent();
        $dispatcher = new Dispatcher($provider);

        // Act
        $result = $dispatcher->dispatch($event);

        // Assert — mutations accumulate: (0+10)*3+5 = 35
        $this->assertSame($event, $result);
        $this->assertSame(35, $event->counter);
    }
}
