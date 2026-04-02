<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune;

use Arcanum\Rune\Output;
use Arcanum\Rune\Prompter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Prompter::class)]
final class PrompterTest extends TestCase
{
    /**
     * @return resource
     */
    private function fakeStdin(string $input): mixed
    {
        $stream = fopen('php://memory', 'r+');
        assert($stream !== false);
        fwrite($stream, $input);
        rewind($stream);

        return $stream;
    }

    public function testAskWritesLabelAndReturnsInput(): void
    {
        // Arrange
        $output = $this->createMock(Output::class);
        $output->expects($this->once())
            ->method('write')
            ->with('Name: ');

        $prompter = new Prompter($output, $this->fakeStdin("Alice\n"));

        // Act
        $result = $prompter->ask('Name:');

        // Assert
        $this->assertSame('Alice', $result);
    }

    public function testAskTrimsWhitespace(): void
    {
        // Arrange
        $output = $this->createStub(Output::class);
        $prompter = new Prompter($output, $this->fakeStdin("  Bob  \n"));

        // Act
        $result = $prompter->ask('Name:');

        // Assert
        $this->assertSame('Bob', $result);
    }

    public function testAskReturnsEmptyOnEof(): void
    {
        // Arrange
        $output = $this->createStub(Output::class);
        $prompter = new Prompter($output, $this->fakeStdin(''));

        // Act
        $result = $prompter->ask('Name:');

        // Assert
        $this->assertSame('', $result);
    }

    public function testSecretWritesLabelAndReturnsInput(): void
    {
        // Arrange — using fake stdin, so stty won't be called
        $output = $this->createMock(Output::class);
        $output->expects($this->once())
            ->method('write')
            ->with('Password: ');

        $prompter = new Prompter($output, $this->fakeStdin("s3cret\n"));

        // Act
        $result = $prompter->secret('Password:');

        // Assert
        $this->assertSame('s3cret', $result);
    }

    public function testSecretReturnsEmptyOnEof(): void
    {
        // Arrange
        $output = $this->createStub(Output::class);
        $prompter = new Prompter($output, $this->fakeStdin(''));

        // Act
        $result = $prompter->secret('Password:');

        // Assert
        $this->assertSame('', $result);
    }
}
