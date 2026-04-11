<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune;

use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\ConsoleOutput;
use Arcanum\Rune\HelpWriter;
use Arcanum\Test\Fixture\Rune\HelpFixtureBasic;
use Arcanum\Test\Fixture\Rune\HelpFixtureDescribed;
use Arcanum\Test\Fixture\Rune\HelpFixtureNoParams;
use Arcanum\Test\Fixture\Rune\HelpFixtureWithBool;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(HelpWriter::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(Description::class)]
final class HelpWriterTest extends TestCase
{
    // ---------------------------------------------------------------
    // Basic output
    // ---------------------------------------------------------------

    public function testShowsCommandNameAndType(): void
    {
        // Arrange
        [$output, $stdout] = $this->makeOutput();
        $writer = new HelpWriter($output);

        // Act
        $writer->write('command:contact:submit', HelpFixtureBasic::class, true);

        // Assert
        $rendered = $this->read($stdout);
        $this->assertStringContainsString('command:contact:submit', $rendered);
        $this->assertStringContainsString('command', $rendered);
    }

    public function testShowsQueryType(): void
    {
        // Arrange
        [$output, $stdout] = $this->makeOutput();
        $writer = new HelpWriter($output);

        // Act
        $writer->write('query:health', HelpFixtureNoParams::class, false);

        // Assert
        $this->assertStringContainsString('query', $this->read($stdout));
    }

    // ---------------------------------------------------------------
    // Parameter listing
    // ---------------------------------------------------------------

    public function testShowsParameterNamesAndTypes(): void
    {
        // Arrange
        [$output, $stdout] = $this->makeOutput();
        $writer = new HelpWriter($output);

        // Act
        $writer->write('command:test', HelpFixtureBasic::class, true);

        // Assert
        $rendered = $this->read($stdout);
        $this->assertStringContainsString('--name', $rendered);
        $this->assertStringContainsString('string', $rendered);
        $this->assertStringContainsString('required', $rendered);
    }

    public function testShowsDefaultValues(): void
    {
        // Arrange
        [$output, $stdout] = $this->makeOutput();
        $writer = new HelpWriter($output);

        // Act
        $writer->write('command:test', HelpFixtureBasic::class, true);

        // Assert
        $rendered = $this->read($stdout);
        $this->assertStringContainsString('--message', $rendered);
        $this->assertStringContainsString('default: ""', $rendered);
    }

    public function testShowsNoParametersMessage(): void
    {
        // Arrange
        [$output, $stdout] = $this->makeOutput();
        $writer = new HelpWriter($output);

        // Act
        $writer->write('query:health', HelpFixtureNoParams::class, false);

        // Assert
        $this->assertStringContainsString('No parameters', $this->read($stdout));
    }

    public function testShowsHandlerOnlyMessage(): void
    {
        // Arrange
        [$output, $stdout] = $this->makeOutput();
        $writer = new HelpWriter($output);

        // Act
        $writer->write('query:widgets:list', 'NonExistent\\Class', false);

        // Assert
        $this->assertStringContainsString('handler-only', $this->read($stdout));
    }

    // ---------------------------------------------------------------
    // #[Description] attribute
    // ---------------------------------------------------------------

    public function testShowsClassDescription(): void
    {
        // Arrange
        [$output, $stdout] = $this->makeOutput();
        $writer = new HelpWriter($output);

        // Act
        $writer->write('command:test', HelpFixtureDescribed::class, true);

        // Assert
        $rendered = $this->read($stdout);
        $this->assertStringContainsString('A described command', $rendered);
        $this->assertStringContainsString('command:test — A described command', $rendered);
    }

    public function testShowsParameterDescriptions(): void
    {
        // Arrange
        [$output, $stdout] = $this->makeOutput();
        $writer = new HelpWriter($output);

        // Act
        $writer->write('command:test', HelpFixtureDescribed::class, true);

        // Assert
        $rendered = $this->read($stdout);
        $this->assertStringContainsString('Full name of the contact', $rendered);
        $this->assertStringContainsString('Email address', $rendered);
    }

    public function testUndescribedParamHasNoDescriptionText(): void
    {
        // Arrange
        [$output, $stdout] = $this->makeOutput();
        $writer = new HelpWriter($output);

        // Act
        $writer->write('command:test', HelpFixtureDescribed::class, true);

        // Assert — message param line ends with default value, no trailing description
        $rendered = $this->read($stdout);
        $lines = explode(\PHP_EOL, $rendered);
        $messageLine = '';
        foreach ($lines as $line) {
            if (str_contains($line, '--message')) {
                $messageLine = $line;
                break;
            }
        }
        $this->assertStringContainsString('default: ""', $messageLine);
        $this->assertStringNotContainsString('Full name', $messageLine);
        $this->assertStringNotContainsString('Email', $messageLine);
    }

    // ---------------------------------------------------------------
    // Auto-generated usage line
    // ---------------------------------------------------------------

    public function testGeneratesUsageLine(): void
    {
        // Arrange
        [$output, $stdout] = $this->makeOutput();
        $writer = new HelpWriter($output);

        // Act
        $writer->write('command:contact:submit', HelpFixtureBasic::class, true);

        // Assert
        $rendered = $this->read($stdout);
        $this->assertStringContainsString('Usage:', $rendered);
        $this->assertStringContainsString('php arcanum command:contact:submit', $rendered);
        $this->assertStringContainsString('--name=<string>', $rendered);
        $this->assertStringContainsString('[--message=<string>]', $rendered);
    }

    public function testBoolParamsShowAsFlagsInUsage(): void
    {
        // Arrange
        [$output, $stdout] = $this->makeOutput();
        $writer = new HelpWriter($output);

        // Act
        $writer->write('query:health', HelpFixtureWithBool::class, false);

        // Assert
        $rendered = $this->read($stdout);
        $this->assertStringContainsString('[--verbose]', $rendered);
        $this->assertStringNotContainsString('--verbose=', $rendered);
    }

    public function testUsageLineForNoParams(): void
    {
        // Arrange
        [$output, $stdout] = $this->makeOutput();
        $writer = new HelpWriter($output);

        // Act
        $writer->write('query:status', HelpFixtureNoParams::class, false);

        // Assert
        $rendered = $this->read($stdout);
        $this->assertStringContainsString('Usage:', $rendered);
        $this->assertStringContainsString('php arcanum query:status', $rendered);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * @return array{ConsoleOutput, resource}
     */
    private function makeOutput(): array
    {
        $stdout = fopen('php://memory', 'r+');
        $this->assertIsResource($stdout);
        $stderr = fopen('php://memory', 'r+');
        $this->assertIsResource($stderr);
        $output = new ConsoleOutput($stdout, $stderr, ansi: false);
        return [$output, $stdout];
    }

    /**
     * @param resource $stream
     */
    private function read(mixed $stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);
        $this->assertIsString($contents);
        return $contents;
    }
}
