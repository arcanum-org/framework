<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune;

use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\BuiltInRegistry;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Test\Fixture\Rune\StubBuiltInCommand;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Container\ContainerInterface;

#[CoversClass(BuiltInRegistry::class)]
#[UsesClass(ExitCode::class)]
final class BuiltInRegistryTest extends TestCase
{
    public function testHasReturnsFalseForUnregistered(): void
    {
        // Arrange
        $registry = new BuiltInRegistry($this->createStub(ContainerInterface::class));

        // Act & Assert
        $this->assertFalse($registry->has('unknown'));
    }

    public function testHasReturnsTrueForRegistered(): void
    {
        // Arrange
        $registry = new BuiltInRegistry($this->createStub(ContainerInterface::class));
        $registry->register('list', StubBuiltInCommand::class);

        // Act & Assert
        $this->assertTrue($registry->has('list'));
    }

    public function testExecuteResolvesAndRuns(): void
    {
        // Arrange
        $command = $this->createMock(BuiltInCommand::class);
        $command->expects($this->once())
            ->method('execute')
            ->willReturn(ExitCode::Success->value);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($command);

        $registry = new BuiltInRegistry($container);
        $registry->register('test', StubBuiltInCommand::class);

        // Act
        $result = $registry->execute('test', new Input('test'), $this->createStub(Output::class));

        // Assert
        $this->assertSame(ExitCode::Success->value, $result);
    }

    public function testExecuteThrowsForUnregistered(): void
    {
        // Arrange
        $registry = new BuiltInRegistry($this->createStub(ContainerInterface::class));

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not registered');
        $registry->execute('unknown', new Input('unknown'), $this->createStub(Output::class));
    }

    public function testNamesReturnsAllRegistered(): void
    {
        // Arrange
        $registry = new BuiltInRegistry($this->createStub(ContainerInterface::class));
        $registry->register('list', StubBuiltInCommand::class);
        $registry->register('help', StubBuiltInCommand::class);

        // Act & Assert
        $this->assertSame(['list', 'help'], $registry->names());
    }

    public function testNamesReturnsEmptyWhenNoneRegistered(): void
    {
        // Arrange
        $registry = new BuiltInRegistry($this->createStub(ContainerInterface::class));

        // Act & Assert
        $this->assertSame([], $registry->names());
    }
}
