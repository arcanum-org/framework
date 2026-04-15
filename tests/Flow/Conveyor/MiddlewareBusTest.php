<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Flow\Conveyor\AcceptedDTO;
use Arcanum\Flow\Conveyor\Command;
use Arcanum\Flow\Conveyor\MiddlewareBus;
use Arcanum\Flow\Conveyor\EmptyDTO;
use Arcanum\Cabinet\Container;
use Arcanum\Codex\Error\Unresolvable;
use Arcanum\Test\Flow\Conveyor\Fixture\BrokenDep;
use Arcanum\Test\Flow\Conveyor\Fixture\DoSomething;
use Arcanum\Test\Flow\Conveyor\Fixture\DoSomethingHandler;
use Arcanum\Test\Flow\Conveyor\Fixture\DoSomethingResult;
use Arcanum\Test\Flow\Conveyor\Fixture\DynamicCommandHandler;
use Arcanum\Test\Flow\Conveyor\Fixture\DynamicCommandResult;
use Arcanum\Test\Flow\Conveyor\Fixture\FireAndForget;
use Arcanum\Test\Flow\Conveyor\Fixture\FireAndForgetHandler;
use Arcanum\Test\Flow\Conveyor\Fixture\LegacyAction;
use Arcanum\Test\Flow\Conveyor\Fixture\LegacyActionHandler;
use Arcanum\Test\Flow\Conveyor\Fixture\MaybeCreate;
use Arcanum\Test\Flow\Conveyor\Fixture\MaybeCreateHandler;
use Arcanum\Test\Flow\Conveyor\Fixture\PostDoSomethingHandler;
use Arcanum\Test\Flow\Conveyor\Fixture\QueueJob;
use Arcanum\Test\Flow\Conveyor\Fixture\QueueJobHandler;
use Arcanum\Test\Flow\Conveyor\Fixture\ValidatedDto;
use Arcanum\Test\Flow\Conveyor\Fixture\ValidatedDtoHandler;
use Arcanum\Flow\Continuum\Continuum;
use Arcanum\Validation\ValidationGuard;
use Psr\Log\LoggerInterface;

#[CoversClass(MiddlewareBus::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\Pipeline::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\StandardProcessor::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\PipelayerSystem::class)]
#[UsesClass(\Arcanum\Flow\Continuum\Continuum::class)]
#[UsesClass(\Arcanum\Flow\Continuum\StandardAdvancer::class)]
#[UsesClass(\Arcanum\Flow\Continuum\ContinuationCollection::class)]
#[UsesClass(Command::class)]
#[UsesClass(\Arcanum\Flow\Conveyor\DynamicDTO::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
#[UsesClass(Container::class)]
#[UsesClass(\Arcanum\Cabinet\PrototypeProvider::class)]
#[UsesClass(\Arcanum\Codex\Event\ClassRequested::class)]
#[UsesClass(\Arcanum\Codex\Resolver::class)]
#[UsesClass(ValidationGuard::class)]
#[UsesClass(\Arcanum\Validation\Validator::class)]
#[UsesClass(\Arcanum\Validation\Rule\NotEmpty::class)]
#[UsesClass(\Arcanum\Quill\Logger::class)]
#[UsesClass(\Arcanum\Quill\Channel::class)]
#[UsesClass(EmptyDTO::class)]
#[UsesClass(AcceptedDTO::class)]
#[UsesClass(\Arcanum\Cabinet\ServiceNotFound::class)]
#[UsesClass(\Arcanum\Toolkit\Strings::class)]
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

    public function testDispatchWithPrefixAutoWiresUnregisteredHandler(): void
    {
        // Arrange — PostDoSomethingHandler exists as a class but is NOT registered
        // in the container. The old has() check would miss it; class_exists() finds it.
        $container = new Container();
        $bus = new MiddlewareBus($container);

        // Act
        $result = $bus->dispatch(new DoSomething('test'), prefix: 'Post');

        // Assert — auto-wired PostDoSomethingHandler prepends "post:"
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
            ->with(
                'Handler not found, falling back to unprefixed',
                $this->callback(fn(array $ctx) => str_contains($ctx['prefixed'], 'DeleteDoSomethingHandler')),
            );

        $bus = new MiddlewareBus(new Container(), debug: true, logger: $loggerMock);

        // Act
        $bus->dispatch(new DoSomething('test'), prefix: 'Delete');
    }

    public function testFallbackLogsWarningInProductionMode(): void
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
            ->with(
                'Handler not found, falling back to unprefixed',
                $this->anything(),
            );

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
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for');
        $bus->dispatch(new EmptyDTO(), prefix: 'Delete');
    }

    public function testDispatchThrowsWhenHandlerClassNotFoundInContainer(): void
    {
        // Arrange — dispatching an object with no handler at all, no prefix
        $bus = new MiddlewareBus(new Container());

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No handler found for');
        $bus->dispatch(new EmptyDTO());
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

    // ---------------------------------------------------------------
    // Void vs nullable return type distinction
    // ---------------------------------------------------------------

    public function testVoidHandlerReturnsEmptyDTO(): void
    {
        // Arrange — FireAndForgetHandler declares `: void`
        $bus = new MiddlewareBus(new Container());

        // Act
        $result = $bus->dispatch(new FireAndForget());

        // Assert — void → EmptyDTO (kernel maps to 204)
        $this->assertInstanceOf(EmptyDTO::class, $result);
    }

    public function testNullableHandlerReturningNullReturnsAcceptedDTO(): void
    {
        // Arrange — QueueJobHandler declares `: ?DoSomethingResult` and returns null
        $bus = new MiddlewareBus(new Container());

        // Act
        $result = $bus->dispatch(new QueueJob());

        // Assert — nullable returning null → AcceptedDTO (kernel maps to 202)
        $this->assertInstanceOf(AcceptedDTO::class, $result);
    }

    public function testNullableHandlerReturningValueReturnsValue(): void
    {
        // Arrange — MaybeCreateHandler declares `: ?DoSomethingResult` and returns a value
        $bus = new MiddlewareBus(new Container());

        // Act
        $result = $bus->dispatch(new MaybeCreate('test'));

        // Assert — nullable returning a value → the value itself (kernel maps to 201)
        $this->assertInstanceOf(DoSomethingResult::class, $result);
        $this->assertSame('test', $result->name);
    }

    public function testHandlerWithNoReturnTypeDeclarationReturnsEmptyDTO(): void
    {
        // Arrange — LegacyActionHandler has no return type declaration
        $bus = new MiddlewareBus(new Container());

        // Act
        $result = $bus->dispatch(new LegacyAction());

        // Assert — no return type → treated as void → EmptyDTO
        $this->assertInstanceOf(EmptyDTO::class, $result);
    }

    // ---------------------------------------------------------------
    // Dependency failure propagation (not swallowed as HandlerNotFound)
    // ---------------------------------------------------------------

    public function testDependencyFailurePropagatesInsteadOfHandlerNotFound(): void
    {
        // Arrange — BrokenDepHandler exists but requires LoggerInterface,
        // which is not registered. The old catch(\Throwable) would wrap this
        // as HandlerNotFound; now it propagates as the real resolution error.
        $bus = new MiddlewareBus(new Container());

        // Act & Assert — should throw an Unresolvable error, NOT HandlerNotFound
        $this->expectException(Unresolvable::class);
        $bus->dispatch(new BrokenDep());
    }

    // -----------------------------------------------------------
    // Validation guard detection
    // -----------------------------------------------------------

    public function testThrowsInDebugWhenValidationAttributesPresentButNoGuard(): void
    {
        // Arrange
        $bus = new MiddlewareBus(new Container(), debug: true);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('has validation rules but no ValidationGuard');

        $bus->dispatch(new ValidatedDto(name: 'test'));
    }

    public function testLogsWarningInProductionWhenValidationAttributesPresentButNoGuard(): void
    {
        // Arrange
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            'ValidationGuard missing for DTO with rules',
            $this->callback(fn(array $ctx) => isset($ctx['dto'])),
        );
        $bus = new MiddlewareBus(new Container(), debug: false, logger: $logger);

        // Act
        $bus->dispatch(new ValidatedDto(name: 'test'));
    }

    public function testNoWarningWhenValidationGuardIsRegistered(): void
    {
        // Arrange
        $bus = new MiddlewareBus(new Container(), debug: true);
        $bus->before(new ValidationGuard());

        // Act — should not throw
        $result = $bus->dispatch(new ValidatedDto(name: 'test'));

        // Assert
        $this->assertInstanceOf(DoSomethingResult::class, $result);
    }

    public function testNoWarningForDtoWithoutValidationAttributes(): void
    {
        // Arrange — DoSomething has no validation attributes
        $bus = new MiddlewareBus(new Container(), debug: true);

        // Act — should not throw even without ValidationGuard
        $result = $bus->dispatch(new DoSomething('test'));

        // Assert
        $this->assertInstanceOf(DoSomethingResult::class, $result);
    }
}
