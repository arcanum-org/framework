<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune;

use Arcanum\Rune\ConsoleOutput;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ConsoleOutput::class)]
final class ConsoleOutputTest extends TestCase
{
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

    public function testWriteOutputsToStdout(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $stderr = $this->createStream();
        $output = new ConsoleOutput($stdout, $stderr, ansi: true);

        // Act
        $output->write('hello');

        // Assert
        $this->assertSame('hello', $this->readStream($stdout));
        $this->assertSame('', $this->readStream($stderr));
    }

    public function testWriteLineAppendsNewline(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: true);

        // Act
        $output->writeLine('hello');

        // Assert
        $this->assertSame('hello' . \PHP_EOL, $this->readStream($stdout));
    }

    public function testErrorOutputsToStderr(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $stderr = $this->createStream();
        $output = new ConsoleOutput($stdout, $stderr, ansi: true);

        // Act
        $output->error('oops');

        // Assert
        $this->assertSame('', $this->readStream($stdout));
        $this->assertSame('oops', $this->readStream($stderr));
    }

    public function testErrorLineAppendsNewline(): void
    {
        // Arrange
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: true);

        // Act
        $output->errorLine('oops');

        // Assert
        $this->assertSame('oops' . \PHP_EOL, $this->readStream($stderr));
    }

    public function testAnsiCodesPassThroughWhenAnsiEnabled(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: true);
        $colored = "\033[31mred text\033[0m";

        // Act
        $output->write($colored);

        // Assert
        $this->assertSame($colored, $this->readStream($stdout));
    }

    public function testAnsiCodesStrippedWhenAnsiDisabled(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $colored = "\033[31mred text\033[0m";

        // Act
        $output->write($colored);

        // Assert
        $this->assertSame('red text', $this->readStream($stdout));
    }

    public function testAnsiStrippingOnStderr(): void
    {
        // Arrange
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);
        $colored = "\033[1;33mwarning\033[0m";

        // Act
        $output->errorLine($colored);

        // Assert
        $this->assertSame('warning' . \PHP_EOL, $this->readStream($stderr));
    }

    public function testAnsiStrippingHandlesMultipleCodes(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $colored = "\033[1m\033[31mbold red\033[0m normal \033[32mgreen\033[0m";

        // Act
        $output->write($colored);

        // Assert
        $this->assertSame('bold red normal green', $this->readStream($stdout));
    }

    public function testAnsiStrippingHandlesTextWithNoAnsiCodes(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);

        // Act
        $output->write('plain text');

        // Assert
        $this->assertSame('plain text', $this->readStream($stdout));
    }

    public function testIsAnsiReturnsTrueWhenEnabled(): void
    {
        // Arrange
        $output = new ConsoleOutput($this->createStream(), $this->createStream(), ansi: true);

        // Act & Assert
        $this->assertTrue($output->isAnsi());
    }

    public function testIsAnsiReturnsFalseWhenDisabled(): void
    {
        // Arrange
        $output = new ConsoleOutput($this->createStream(), $this->createStream(), ansi: false);

        // Act & Assert
        $this->assertFalse($output->isAnsi());
    }

    public function testAutoDetectsNonTtyAsNoAnsi(): void
    {
        // Arrange — php://memory is not a TTY
        $output = new ConsoleOutput($this->createStream(), $this->createStream());

        // Act & Assert
        $this->assertFalse($output->isAnsi());
    }

    public function testMultipleWritesConcatenate(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: true);

        // Act
        $output->write('hello ');
        $output->write('world');

        // Assert
        $this->assertSame('hello world', $this->readStream($stdout));
    }

    public function testEmptyStringWrite(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: true);

        // Act
        $output->write('');

        // Assert
        $this->assertSame('', $this->readStream($stdout));
    }

    public function testEmptyStringWriteLine(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: true);

        // Act
        $output->writeLine('');

        // Assert
        $this->assertSame(\PHP_EOL, $this->readStream($stdout));
    }
}
