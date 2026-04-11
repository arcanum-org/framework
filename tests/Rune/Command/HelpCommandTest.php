<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Command;

use Arcanum\Atlas\CliRouter;
use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\Route;
use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\Command\HelpCommand;
use Arcanum\Rune\ConsoleOutput;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\HelpWriter;
use Arcanum\Rune\Input;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(HelpCommand::class)]
#[UsesClass(CliRouter::class)]
#[UsesClass(ConventionResolver::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(Description::class)]
#[UsesClass(ExitCode::class)]
#[UsesClass(HelpWriter::class)]
#[UsesClass(Input::class)]
#[UsesClass(Route::class)]
#[UsesClass(Strings::class)]
final class HelpCommandTest extends TestCase
{
    public function testRendersHelpForValidCommand(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $router = new CliRouter(new ConventionResolver(rootNamespace: 'Arcanum\\Test\\Fixture'));
        $command = new HelpCommand($router);

        $input = new Input('help', arguments: ['query:shop:products']);

        // Act
        $exitCode = $command->execute($input, $output);

        // Assert
        $this->assertSame(ExitCode::Success->value, $exitCode);
        $rendered = $this->readStream($stdout);
        $this->assertStringContainsString('query:shop:products', $rendered);
    }

    public function testReturnsInvalidWhenNoTargetGiven(): void
    {
        // Arrange
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);
        $router = new CliRouter(new ConventionResolver(rootNamespace: 'Arcanum\\Test\\Fixture'));
        $command = new HelpCommand($router);

        $input = new Input('help');

        // Act
        $exitCode = $command->execute($input, $output);

        // Assert
        $this->assertSame(ExitCode::Invalid->value, $exitCode);
        $this->assertStringContainsString('Usage:', $this->readStream($stderr));
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
