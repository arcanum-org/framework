<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune;

use Arcanum\Rune\Input;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Input::class)]
final class InputTest extends TestCase
{
    public function testFromArgvExtractsCommandName(): void
    {
        // Arrange
        $argv = ['bin/arcanum', 'query:health'];

        // Act
        $input = Input::fromArgv($argv);

        // Assert
        $this->assertSame('query:health', $input->command());
    }

    public function testFromArgvDiscardsScriptName(): void
    {
        // Arrange
        $argv = ['bin/arcanum', 'list'];

        // Act
        $input = Input::fromArgv($argv);

        // Assert
        $this->assertSame('list', $input->command());
        $this->assertSame([], $input->arguments());
    }

    public function testFromArgvReturnsEmptyCommandWhenNoArguments(): void
    {
        // Arrange
        $argv = ['bin/arcanum'];

        // Act
        $input = Input::fromArgv($argv);

        // Assert
        $this->assertSame('', $input->command());
    }

    public function testFromArgvReturnsEmptyCommandForEmptyArray(): void
    {
        // Arrange & Act
        $input = Input::fromArgv([]);

        // Assert
        $this->assertSame('', $input->command());
    }

    public function testFromArgvParsesNamedOptionWithEqualsSign(): void
    {
        // Arrange
        $argv = ['bin/arcanum', 'command:contact:submit', '--name=Jo', '--email=jo@test.com'];

        // Act
        $input = Input::fromArgv($argv);

        // Assert
        $this->assertSame('Jo', $input->option('name'));
        $this->assertSame('jo@test.com', $input->option('email'));
    }

    public function testFromArgvParsesNamedOptionWithSpaceSeparator(): void
    {
        // Arrange
        $argv = ['bin/arcanum', 'command:contact:submit', '--name', 'Jo', '--email', 'jo@test.com'];

        // Act
        $input = Input::fromArgv($argv);

        // Assert
        $this->assertSame('Jo', $input->option('name'));
        $this->assertSame('jo@test.com', $input->option('email'));
    }

    public function testFromArgvParsesBooleanFlags(): void
    {
        // Arrange
        $argv = ['bin/arcanum', 'query:health', '--verbose', '--no-ansi'];

        // Act
        $input = Input::fromArgv($argv);

        // Assert
        $this->assertTrue($input->hasFlag('verbose'));
        $this->assertTrue($input->hasFlag('no-ansi'));
        $this->assertFalse($input->hasFlag('quiet'));
    }

    public function testFromArgvParsesPositionalArguments(): void
    {
        // Arrange
        $argv = ['bin/arcanum', 'list', 'commands', 'queries'];

        // Act
        $input = Input::fromArgv($argv);

        // Assert
        $this->assertSame(['commands', 'queries'], $input->arguments());
        $this->assertSame('commands', $input->argument(0));
        $this->assertSame('queries', $input->argument(1));
    }

    public function testFromArgvParsesMixedOptionsAndFlags(): void
    {
        // Arrange
        $argv = ['bin/arcanum', 'command:contact:submit', '--name=Jo', '--verbose', '--email', 'jo@test.com'];

        // Act
        $input = Input::fromArgv($argv);

        // Assert
        $this->assertSame('Jo', $input->option('name'));
        $this->assertSame('jo@test.com', $input->option('email'));
        $this->assertTrue($input->hasFlag('verbose'));
    }

    public function testFromArgvTreatsDoubledDashAsEndOfOptions(): void
    {
        // Arrange
        $argv = ['bin/arcanum', 'command:run', '--verbose', '--', '--not-a-flag', 'some-value'];

        // Act
        $input = Input::fromArgv($argv);

        // Assert
        $this->assertTrue($input->hasFlag('verbose'));
        $this->assertFalse($input->hasFlag('not-a-flag'));
        $this->assertSame(['--not-a-flag', 'some-value'], $input->arguments());
    }

    public function testFromArgvHandlesEmptyEqualsValue(): void
    {
        // Arrange
        $argv = ['bin/arcanum', 'command:run', '--name='];

        // Act
        $input = Input::fromArgv($argv);

        // Assert
        $this->assertSame('', $input->option('name'));
        $this->assertTrue($input->hasOption('name'));
    }

    public function testOptionReturnsDefaultWhenNotPresent(): void
    {
        // Arrange
        $input = Input::fromArgv(['bin/arcanum', 'query:health']);

        // Act
        $result = $input->option('format', 'table');

        // Assert
        $this->assertSame('table', $result);
    }

    public function testOptionReturnsNullWhenNotPresentAndNoDefault(): void
    {
        // Arrange
        $input = Input::fromArgv(['bin/arcanum', 'query:health']);

        // Act
        $result = $input->option('format');

        // Assert
        $this->assertNull($result);
    }

    public function testHasOptionReturnsTrueForPresentOption(): void
    {
        // Arrange
        $input = Input::fromArgv(['bin/arcanum', 'query:health', '--format=json']);

        // Act & Assert
        $this->assertTrue($input->hasOption('format'));
    }

    public function testHasOptionReturnsFalseForAbsentOption(): void
    {
        // Arrange
        $input = Input::fromArgv(['bin/arcanum', 'query:health']);

        // Act & Assert
        $this->assertFalse($input->hasOption('format'));
    }

    public function testArgumentReturnsNullForOutOfBoundsIndex(): void
    {
        // Arrange
        $input = Input::fromArgv(['bin/arcanum', 'list']);

        // Act
        $result = $input->argument(0);

        // Assert
        $this->assertNull($result);
    }

    public function testFlagsReturnsAllFlags(): void
    {
        // Arrange
        $input = Input::fromArgv(['bin/arcanum', 'query:health', '--verbose', '--no-ansi']);

        // Act
        $flags = $input->flags();

        // Assert
        $this->assertSame(['verbose' => true, 'no-ansi' => true], $flags);
    }

    public function testOptionsReturnsAllOptions(): void
    {
        // Arrange
        $input = Input::fromArgv(['bin/arcanum', 'command:submit', '--name=Jo', '--email=jo@test.com']);

        // Act
        $options = $input->options();

        // Assert
        $this->assertSame(['name' => 'Jo', 'email' => 'jo@test.com'], $options);
    }

    public function testConstructorAcceptsDirectValues(): void
    {
        // Arrange & Act
        $input = new Input(
            command: 'query:health',
            arguments: ['arg1'],
            options: ['format' => 'json'],
            flags: ['verbose' => true],
        );

        // Assert
        $this->assertSame('query:health', $input->command());
        $this->assertSame(['arg1'], $input->arguments());
        $this->assertSame('json', $input->option('format'));
        $this->assertTrue($input->hasFlag('verbose'));
    }

    public function testFromArgvDistinguishesFlagFollowedByFlag(): void
    {
        // Arrange — two flags in a row, neither should be treated as a value
        $argv = ['bin/arcanum', 'query:health', '--verbose', '--debug'];

        // Act
        $input = Input::fromArgv($argv);

        // Assert
        $this->assertTrue($input->hasFlag('verbose'));
        $this->assertTrue($input->hasFlag('debug'));
        $this->assertSame([], $input->options());
    }

    public function testFromArgvHandlesPositionalArgumentsBetweenOptions(): void
    {
        // Arrange
        $argv = ['bin/arcanum', 'command:run', 'file.txt', '--verbose'];

        // Act
        $input = Input::fromArgv($argv);

        // Assert
        $this->assertSame(['file.txt'], $input->arguments());
        $this->assertTrue($input->hasFlag('verbose'));
    }

    public function testFromArgvLastOptionValueWinsForDuplicateKeys(): void
    {
        // Arrange
        $argv = ['bin/arcanum', 'command:run', '--format=json', '--format=csv'];

        // Act
        $input = Input::fromArgv($argv);

        // Assert
        $this->assertSame('csv', $input->option('format'));
    }
}
