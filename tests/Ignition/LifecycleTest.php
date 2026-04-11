<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition;

use Arcanum\Cabinet\Container;
use Arcanum\Glitch\ExceptionHandler;
use Arcanum\Ignition\Lifecycle;
use Psr\EventDispatcher\EventDispatcherInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Lifecycle::class)]
#[UsesClass(Container::class)]
final class LifecycleTest extends TestCase
{
    public function testDispatchDelegatesToEventDispatcher(): void
    {
        $event = new \stdClass();
        $dispatched = null;

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($event)
            ->willReturnCallback(function (object $e) use (&$dispatched) {
                $dispatched = $e;
                return $e;
            });

        $container = new Container();
        $container->instance(EventDispatcherInterface::class, $dispatcher);

        $lifecycle = new Lifecycle($container);
        $result = $lifecycle->dispatch($event);

        $this->assertSame($event, $dispatched);
        $this->assertSame($event, $result);
    }

    public function testDispatchReturnsEventWhenNoDispatcherRegistered(): void
    {
        $container = new Container();
        $event = new \stdClass();

        $lifecycle = new Lifecycle($container);
        $result = $lifecycle->dispatch($event);

        $this->assertSame($event, $result);
    }

    public function testReportDelegatesToExceptionHandler(): void
    {
        $exception = new \RuntimeException('test');
        $reported = null;

        $handler = $this->createMock(ExceptionHandler::class);
        $handler->expects($this->once())
            ->method('handleException')
            ->with($exception)
            ->willReturnCallback(function (\Throwable $e) use (&$reported) {
                $reported = $e;
            });

        $container = new Container();
        $container->instance(ExceptionHandler::class, $handler);

        $lifecycle = new Lifecycle($container);
        $lifecycle->report($exception);

        $this->assertSame($exception, $reported);
    }

    public function testReportDoesNothingWhenNoHandlerRegistered(): void
    {
        $container = new Container();

        $lifecycle = new Lifecycle($container);
        $lifecycle->report(new \RuntimeException('test'));

        $this->addToAssertionCount(1);
    }
}
