<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Flow\Conveyor\Command;
use Arcanum\Flow\Conveyor\MiddlewareBus;
use Arcanum\Flow\Conveyor\EmptyDTO;
use Arcanum\Cabinet\Container;
use Arcanum\Test\Flow\Conveyor\Fixture\DoSomething;
use Arcanum\Test\Flow\Conveyor\Fixture\DoSomethingHandler;
use Arcanum\Test\Flow\Conveyor\Fixture\DoSomethingResult;
use Arcanum\Test\Flow\Conveyor\Fixture\DynamicCommandHandler;
use Arcanum\Test\Flow\Conveyor\Fixture\DynamicCommandResult;
use Arcanum\Test\Flow\Conveyor\Fixture\PostDoSomethingHandler;
use Arcanum\Flow\Continuum\Continuum;

#[CoversClass(MiddlewareBus::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\Pipeline::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\StandardProcessor::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\PipelayerSystem::class)]
#[UsesClass(\Arcanum\Flow\Continuum\Continuum::class)]
#[UsesClass(\Arcanum\Flow\Continuum\StandardAdvancer::class)]
#[UsesClass(\Arcanum\Flow\Continuum\ContinuationCollection::class)]
#[UsesClass(Command::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
#[UsesClass(Container::class)]
#[UsesClass(\Arcanum\Cabinet\PrototypeProvider::class)]
#[UsesClass(\Arcanum\Codex\Event\ClassRequested::class)]
#[UsesClass(\Arcanum\Codex\Resolver::class)]
#[UsesClass(\Arcanum\Quill\Logger::class)]
#[UsesClass(\Arcanum\Quill\Channel::class)]
#[UsesClass(EmptyDTO::class)]
final class MiddlewareBusTest extends TestCase
{
    public function testDispatchHappyPath(): void
    {
        // Arrange
        $command = new DoSomething('test');
        $response = new DoSomethingResult('test');

        /** @var Continuum&\PHPUnit\Framework\MockObject\MockObject */
        $dispatchFlow = $this->getMockBuilder(Continuum::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            ->getMock();

        $dispatchFlow->expects($this->once())
            ->method('__invoke')
            ->with($command)
            ->willReturn($command);

        /** @var Continuum&\PHPUnit\Framework\MockObject\MockObject */
        $responseFlow = $this->getMockBuilder(Continuum::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['__invoke'])
            ->getMock();

        $responseFlow->expects($this->once())
            ->method('__invoke')
            ->with($response)
            ->willReturn($response);


        /** @var Container&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(Container::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get'])
            ->getMock();

        $container->expects($this->once())
            ->method('get')
            ->with(DoSomethingHandler::class)
            ->willReturn(new DoSomethingHandler());

        $bus = new MiddlewareBus($container, $dispatchFlow, $responseFlow);

        // Act
        $result = $bus->dispatch($command);

        // Assert
        $this->assertInstanceOf(DoSomethingResult::class, $result);
        $this->assertSame('test', $result->name);
    }

    // ---------------------------------------------------------------
    // Empty prefix (default behavior, no change from current)
    // ---------------------------------------------------------------

    public function testDispatchWithEmptyPrefixUsesDefaultHandler(): void
    {
        // Arrange
        $bus = new MiddlewareBus(new Container());

        // Act
        $result = $bus->dispatch(new DoSomething('test'), prefix: '');

        // Assert
        $this->assertInstanceOf(DoSomethingResult::class, $result);
        $this->assertSame('test', $result->name);
    }

    // ---------------------------------------------------------------
    // Prefix resolving to a prefixed handler
    // ---------------------------------------------------------------

    public function testDispatchWithPrefixResolvesToPrefixedHandler(): void
    {
        // Arrange
        $container = new Container();
        $container->service(PostDoSomethingHandler::class);
        $bus = new MiddlewareBus($container);

        // Act
        $result = $bus->dispatch(new DoSomething('test'), prefix: 'Post');

        // Assert
        $this->assertInstanceOf(DoSomethingResult::class, $result);
        $this->assertSame('post:test', $result->name);
    }

    // ---------------------------------------------------------------
    // Prefix falling back to unprefixed handler
    // ---------------------------------------------------------------

    public function testDispatchWithPrefixFallsBackToUnprefixedHandler(): void
    {
        // Arrange — no DeleteDoSomethingHandler registered, falls back to DoSomethingHandler
        $bus = new MiddlewareBus(new Container());

        // Act
        $result = $bus->dispatch(new DoSomething('test'), prefix: 'Delete');

        // Assert
        $this->assertInstanceOf(DoSomethingResult::class, $result);
        $this->assertSame('test', $result->name);
    }

    // ---------------------------------------------------------------
    // Debug-mode warning log on fallback
    // ---------------------------------------------------------------

    public function testFallbackLogsWarningInDebugMode(): void
    {
        // Arrange
        $channel = new \Arcanum\Quill\Channel(new \Monolog\Logger('test'));

        /** @var \Arcanum\Quill\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $loggerMock = $this->getMockBuilder(\Arcanum\Quill\Logger::class)
            ->setConstructorArgs([$channel])
            ->onlyMethods(['warning'])
            ->getMock();

        $loggerMock->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('DeleteDoSomethingHandler'));

        $bus = new MiddlewareBus(new Container(), debug: true, logger: $loggerMock);

        // Act
        $bus->dispatch(new DoSomething('test'), prefix: 'Delete');
    }

    public function testFallbackDoesNotLogInProductionMode(): void
    {
        // Arrange
        $channel = new \Arcanum\Quill\Channel(new \Monolog\Logger('test'));

        /** @var \Arcanum\Quill\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $loggerMock = $this->getMockBuilder(\Arcanum\Quill\Logger::class)
            ->setConstructorArgs([$channel])
            ->onlyMethods(['warning'])
            ->getMock();

        $loggerMock->expects($this->never())
            ->method('warning');

        $bus = new MiddlewareBus(new Container(), debug: false, logger: $loggerMock);

        // Act
        $bus->dispatch(new DoSomething('test'), prefix: 'Delete');
    }

    public function testFallbackDoesNotLogWithoutLogger(): void
    {
        // Arrange
        $bus = new MiddlewareBus(new Container(), debug: true);

        // Act — should not throw, just silently fall back
        $result = $bus->dispatch(new DoSomething('test'), prefix: 'Delete');

        // Assert
        $this->assertInstanceOf(DoSomethingResult::class, $result);
    }

    // ---------------------------------------------------------------
    // Neither prefixed nor unprefixed handler exists
    // ---------------------------------------------------------------

    public function testDispatchWithPrefixThrowsWhenNoHandlerExists(): void
    {
        // Arrange — dispatching an object with no handler at all
        $bus = new MiddlewareBus(new Container());

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $bus->dispatch(new EmptyDTO(), prefix: 'Delete');
    }

    // ---------------------------------------------------------------
    // HandlerProxy — dynamic DTOs
    // ---------------------------------------------------------------

    public function testDispatchUsesHandlerProxyBaseName(): void
    {
        // Arrange — Command with a virtual class name pointing to DynamicCommand
        $bus = new MiddlewareBus(new Container());
        $command = new Command(
            'Arcanum\Test\Flow\Conveyor\Fixture\DynamicCommand',
            ['name' => 'Alice'],
        );

        // Act — should resolve DynamicCommandHandler via the proxy base name
        $result = $bus->dispatch($command);

        // Assert
        $this->assertInstanceOf(DynamicCommandResult::class, $result);
        $this->assertSame('Alice', $result->name);
    }
}
