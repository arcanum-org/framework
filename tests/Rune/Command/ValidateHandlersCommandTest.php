<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Command;

use Arcanum\Rune\Command\ValidateHandlersCommand;
use Arcanum\Rune\ConsoleOutput;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ValidateHandlersCommand::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(ExitCode::class)]
#[UsesClass(Input::class)]
final class ValidateHandlersCommandTest extends TestCase
{
    public function testReportsSuccessWhenAllHandlersExist(): void
    {
        // Arrange — Integration fixture has Command/Submit+SubmitHandler and Query/Status+StatusHandler
        $sourceDir = dirname(__DIR__, 2) . '/Fixture/Integration';
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $command = new ValidateHandlersCommand(
            sourceDirectory: $sourceDir,
            rootNamespace: 'Arcanum\\Test\\Fixture\\Integration',
        );

        $input = new Input('validate:handlers');

        // Act
        $exitCode = $command->execute($input, $output);

        // Assert
        $rendered = $this->readStream($stdout);
        $this->assertSame(ExitCode::Success->value, $exitCode, $rendered);
        $this->assertStringContainsString('All', $rendered);
    }

    public function testReportsFailureForMissingDirectory(): void
    {
        // Arrange
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);
        $command = new ValidateHandlersCommand(
            sourceDirectory: '/nonexistent/path',
            rootNamespace: 'App\\Domain',
        );

        $input = new Input('validate:handlers');

        // Act
        $exitCode = $command->execute($input, $output);

        // Assert
        $this->assertSame(ExitCode::Failure->value, $exitCode);
        $this->assertStringContainsString('Directory not found', $this->readStream($stderr));
    }

    /**
     * @return resource
     */
    private function createStream(): mixed
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function readStream(mixed $stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);
        $this->assertIsString($contents);
        return $contents;
    }
}
