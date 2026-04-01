<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\TableFormatter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TableFormatter::class)]
final class TableFormatterTest extends TestCase
{
    // ---------------------------------------------------------------
    // List of associative arrays (tabular)
    // ---------------------------------------------------------------

    public function testFormatsListOfArraysAsTable(): void
    {
        // Arrange
        $formatter = new TableFormatter();
        $data = [
            ['id' => 1, 'name' => 'Jo'],
            ['id' => 2, 'name' => 'Sam'],
        ];

        // Act
        $result = $formatter->format($data);

        // Assert
        $this->assertStringContainsString('│ id │ name │', $result);
        $this->assertStringContainsString('│ 1  │ Jo   │', $result);
        $this->assertStringContainsString('│ 2  │ Sam  │', $result);
        $this->assertStringStartsWith('┌', $result);
        $this->assertStringEndsWith('┘', $result);
    }

    public function testFormatsListOfObjectsAsTable(): void
    {
        // Arrange
        $formatter = new TableFormatter();
        $data = [
            (object) ['status' => 'ok', 'code' => 200],
            (object) ['status' => 'fail', 'code' => 500],
        ];

        // Act
        $result = $formatter->format($data);

        // Assert
        $this->assertStringContainsString('│ status │ code │', $result);
        $this->assertStringContainsString('│ ok     │ 200  │', $result);
        $this->assertStringContainsString('│ fail   │ 500  │', $result);
    }

    public function testTableHasCorrectBorderCharacters(): void
    {
        // Arrange
        $formatter = new TableFormatter();
        $data = [['a' => '1', 'b' => '2']];

        // Act
        $result = $formatter->format($data);

        // Assert
        $this->assertStringContainsString('┌', $result);
        $this->assertStringContainsString('┐', $result);
        $this->assertStringContainsString('├', $result);
        $this->assertStringContainsString('┤', $result);
        $this->assertStringContainsString('└', $result);
        $this->assertStringContainsString('┘', $result);
        $this->assertStringContainsString('┬', $result);
        $this->assertStringContainsString('┼', $result);
        $this->assertStringContainsString('┴', $result);
    }

    // ---------------------------------------------------------------
    // Associative array (key-value)
    // ---------------------------------------------------------------

    public function testFormatsAssociativeArrayAsKeyValueTable(): void
    {
        // Arrange
        $formatter = new TableFormatter();
        $data = ['status' => 'ok', 'version' => '1.0'];

        // Act
        $result = $formatter->format($data);

        // Assert
        $this->assertStringContainsString('│ key     │ value │', $result);
        $this->assertStringContainsString('│ status  │ ok    │', $result);
        $this->assertStringContainsString('│ version │ 1.0   │', $result);
    }

    // ---------------------------------------------------------------
    // List of scalars
    // ---------------------------------------------------------------

    public function testFormatsListOfScalarsAsSingleColumnTable(): void
    {
        // Arrange
        $formatter = new TableFormatter();
        $data = ['alpha', 'beta', 'gamma'];

        // Act
        $result = $formatter->format($data);

        // Assert
        $this->assertStringContainsString('│ value │', $result);
        $this->assertStringContainsString('│ alpha │', $result);
        $this->assertStringContainsString('│ beta  │', $result);
    }

    // ---------------------------------------------------------------
    // Object input
    // ---------------------------------------------------------------

    public function testFormatsSingleObjectAsKeyValueTable(): void
    {
        // Arrange
        $formatter = new TableFormatter();
        $data = (object) ['name' => 'Jo', 'age' => '30'];

        // Act
        $result = $formatter->format($data);

        // Assert
        $this->assertStringContainsString('│ key  │ value │', $result);
        $this->assertStringContainsString('│ name │ Jo    │', $result);
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    public function testReturnsEmptyStringForEmptyArray(): void
    {
        // Arrange
        $formatter = new TableFormatter();

        // Act & Assert
        $this->assertSame('', $formatter->format([]));
    }

    public function testReturnsEmptyStringForNull(): void
    {
        // Arrange
        $formatter = new TableFormatter();

        // Act & Assert
        $this->assertSame('', $formatter->format(null));
    }

    public function testHandlesBooleanValues(): void
    {
        // Arrange
        $formatter = new TableFormatter();
        $data = [['active' => true, 'deleted' => false]];

        // Act
        $result = $formatter->format($data);

        // Assert
        $this->assertStringContainsString('true', $result);
        $this->assertStringContainsString('false', $result);
    }

    public function testHandlesNestedArrayValues(): void
    {
        // Arrange
        $formatter = new TableFormatter();
        $data = [['name' => 'Jo', 'tags' => ['a', 'b']]];

        // Act
        $result = $formatter->format($data);

        // Assert
        $this->assertStringContainsString('["a","b"]', $result);
    }
}
