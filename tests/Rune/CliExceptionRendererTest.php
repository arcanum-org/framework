<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune;

use Arcanum\Rune\CliExceptionRenderer;
use Arcanum\Rune\ConsoleOutput;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CliExceptionRenderer::class)]
#[UsesClass(ConsoleOutput::class)]
final class CliExceptionRendererTest extends TestCase
{
    // ---------------------------------------------------------------
    // Production mode
    // ---------------------------------------------------------------

    public function testProductionModeShowsOnlyMessage(): void
    {
        // Arrange
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);
        $renderer = new CliExceptionRenderer($output, debug: false);

        // Act
        $renderer->render(new \RuntimeException('Something went wrong'));

        // Assert
        $rendered = $this->readStream($stderr);
        $this->assertSame('Error: Something went wrong' . \PHP_EOL, $rendered);
    }

    public function testProductionModeDoesNotShowStackTrace(): void
    {
        // Arrange
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);
        $renderer = new CliExceptionRenderer($output, debug: false);

        // Act
        $renderer->render(new \RuntimeException('fail'));

        // Assert
        $rendered = $this->readStream($stderr);
        $this->assertStringNotContainsString('#0', $rendered);
        $this->assertStringNotContainsString('RuntimeException', $rendered);
    }

    // ---------------------------------------------------------------
    // Debug mode
    // ---------------------------------------------------------------

    public function testDebugModeShowsExceptionClass(): void
    {
        // Arrange
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);
        $renderer = new CliExceptionRenderer($output, debug: true);

        // Act
        $renderer->render(new \InvalidArgumentException('bad input'));

        // Assert
        $rendered = $this->readStream($stderr);
        $this->assertStringContainsString('InvalidArgumentException', $rendered);
        $this->assertStringContainsString('bad input', $rendered);
    }

    public function testDebugModeShowsFileAndLine(): void
    {
        // Arrange
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);
        $renderer = new CliExceptionRenderer($output, debug: true);

        // Act
        $renderer->render(new \RuntimeException('fail'));

        // Assert
        $rendered = $this->readStream($stderr);
        $this->assertStringContainsString('CliExceptionRendererTest.php', $rendered);
        $this->assertStringContainsString('  in ', $rendered);
    }

    public function testDebugModeShowsStackTrace(): void
    {
        // Arrange
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);
        $renderer = new CliExceptionRenderer($output, debug: true);

        // Act
        $renderer->render(new \RuntimeException('fail'));

        // Assert
        $rendered = $this->readStream($stderr);
        $this->assertStringContainsString('#0', $rendered);
    }

    public function testDebugModeShowsPreviousException(): void
    {
        // Arrange
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);
        $renderer = new CliExceptionRenderer($output, debug: true);
        $previous = new \LogicException('root cause');
        $exception = new \RuntimeException('surface error', 0, $previous);

        // Act
        $renderer->render($exception);

        // Assert
        $rendered = $this->readStream($stderr);
        $this->assertStringContainsString('Caused by:', $rendered);
        $this->assertStringContainsString('LogicException', $rendered);
        $this->assertStringContainsString('root cause', $rendered);
    }

    // ---------------------------------------------------------------
    // Default debug mode
    // ---------------------------------------------------------------

    public function testDefaultIsProductionMode(): void
    {
        // Arrange
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);
        $renderer = new CliExceptionRenderer($output);

        // Act
        $renderer->render(new \RuntimeException('test'));

        // Assert — production mode: no class name shown
        $rendered = $this->readStream($stderr);
        $this->assertStringNotContainsString('RuntimeException', $rendered);
        $this->assertStringContainsString('Error: test', $rendered);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

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
