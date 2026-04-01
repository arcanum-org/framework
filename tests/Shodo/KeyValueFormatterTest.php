<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\KeyValueFormatter;
use Arcanum\Shodo\TableFormatter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(KeyValueFormatter::class)]
#[UsesClass(TableFormatter::class)]
final class KeyValueFormatterTest extends TestCase
{
    // ---------------------------------------------------------------
    // Associative array → key-value pairs
    // ---------------------------------------------------------------

    public function testFormatsAssociativeArrayAsKeyValuePairs(): void
    {
        // Arrange
        $formatter = new KeyValueFormatter();

        // Act
        $result = $formatter->format(['status' => 'ok', 'version' => '1.0']);

        // Assert
        $this->assertStringContainsString('status', $result);
        $this->assertStringContainsString('ok', $result);
        $this->assertStringContainsString('version', $result);
        $this->assertStringContainsString('1.0', $result);
    }

    public function testKeyValuePairsAreAligned(): void
    {
        // Arrange
        $formatter = new KeyValueFormatter();

        // Act
        $result = $formatter->format(['a' => '1', 'long_key' => '2']);

        // Assert — both values should be at the same column
        $lines = explode(\PHP_EOL, $result);
        $pos1 = strpos($lines[0], '1');
        $pos2 = strpos($lines[1], '2');
        $this->assertSame($pos1, $pos2);
    }

    // ---------------------------------------------------------------
    // Object → key-value pairs
    // ---------------------------------------------------------------

    public function testFormatsObjectAsKeyValuePairs(): void
    {
        // Arrange
        $formatter = new KeyValueFormatter();
        $data = (object) ['name' => 'Jo', 'email' => 'jo@test.com'];

        // Act
        $result = $formatter->format($data);

        // Assert
        $this->assertStringContainsString('name', $result);
        $this->assertStringContainsString('Jo', $result);
        $this->assertStringContainsString('email', $result);
    }

    // ---------------------------------------------------------------
    // List of arrays → table (delegates to TableFormatter)
    // ---------------------------------------------------------------

    public function testFormatsListOfArraysAsTable(): void
    {
        // Arrange
        $formatter = new KeyValueFormatter();
        $data = [
            ['id' => 1, 'name' => 'Jo'],
            ['id' => 2, 'name' => 'Sam'],
        ];

        // Act
        $result = $formatter->format($data);

        // Assert — should have table borders
        $this->assertStringContainsString('┌', $result);
        $this->assertStringContainsString('│', $result);
        $this->assertStringContainsString('Jo', $result);
    }

    public function testFormatsListOfObjectsAsTable(): void
    {
        // Arrange
        $formatter = new KeyValueFormatter();
        $data = [
            (object) ['x' => 1],
            (object) ['x' => 2],
        ];

        // Act
        $result = $formatter->format($data);

        // Assert
        $this->assertStringContainsString('┌', $result);
    }

    // ---------------------------------------------------------------
    // Scalar → plain text
    // ---------------------------------------------------------------

    public function testFormatsStringScalar(): void
    {
        // Arrange
        $formatter = new KeyValueFormatter();

        // Act & Assert
        $this->assertSame('hello', $formatter->format('hello'));
    }

    public function testFormatsIntegerScalar(): void
    {
        // Arrange
        $formatter = new KeyValueFormatter();

        // Act & Assert
        $this->assertSame('42', $formatter->format(42));
    }

    public function testFormatsBooleanTrue(): void
    {
        // Arrange
        $formatter = new KeyValueFormatter();

        // Act & Assert
        $this->assertSame('1', $formatter->format(true));
    }

    // ---------------------------------------------------------------
    // Empty / null
    // ---------------------------------------------------------------

    public function testFormatsNullAsEmptyString(): void
    {
        // Arrange
        $formatter = new KeyValueFormatter();

        // Act & Assert
        $this->assertSame('', $formatter->format(null));
    }

    public function testFormatsEmptyArrayAsEmptyString(): void
    {
        // Arrange
        $formatter = new KeyValueFormatter();

        // Act & Assert
        $this->assertSame('', $formatter->format([]));
    }

    // ---------------------------------------------------------------
    // Nested values in key-value mode
    // ---------------------------------------------------------------

    public function testNestedArrayInKeyValueFormattedAsJson(): void
    {
        // Arrange
        $formatter = new KeyValueFormatter();

        // Act
        $result = $formatter->format(['tags' => ['a', 'b']]);

        // Assert
        $this->assertStringContainsString('["a","b"]', $result);
    }
}
