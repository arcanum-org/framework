<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\CsvFormatter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(CsvFormatter::class)]
final class CsvFormatterTest extends TestCase
{
    public function testFormatReturnsNonEmptyOutput(): void
    {
        // Arrange
        $formatter = new CsvFormatter();

        // Act
        $result = $formatter->format([['name' => 'Alice']]);

        // Assert
        $this->assertNotSame('', $result);
    }

    public function testFormatsTabularData(): void
    {
        // Arrange
        $formatter = new CsvFormatter();
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ];

        // Act
        $lines = explode("\n", trim($formatter->format($data)));

        // Assert
        $this->assertSame('name,age', $lines[0]);
        $this->assertSame('Alice,30', $lines[1]);
        $this->assertSame('Bob,25', $lines[2]);
    }

    public function testFormatsAssociativeArrayAsKeyValuePairs(): void
    {
        // Arrange
        $formatter = new CsvFormatter();

        // Act
        $lines = explode("\n", trim($formatter->format(['status' => 'ok', 'version' => '1.0'])));

        // Assert
        $this->assertSame('key,value', $lines[0]);
        $this->assertSame('status,ok', $lines[1]);
        $this->assertSame('version,1.0', $lines[2]);
    }

    public function testFormatsSequentialScalarArray(): void
    {
        // Arrange
        $formatter = new CsvFormatter();

        // Act
        $lines = explode("\n", trim($formatter->format(['apple', 'banana', 'cherry'])));

        // Assert
        $this->assertSame('value', $lines[0]);
        $this->assertSame('apple', $lines[1]);
        $this->assertSame('banana', $lines[2]);
        $this->assertSame('cherry', $lines[3]);
    }

    public function testFormatsScalarValue(): void
    {
        // Arrange
        $formatter = new CsvFormatter();

        // Act
        $lines = explode("\n", trim($formatter->format('hello')));

        // Assert
        $this->assertSame('value', $lines[0]);
        $this->assertSame('hello', $lines[1]);
    }

    public function testFormatsEmptyArray(): void
    {
        // Arrange
        $formatter = new CsvFormatter();

        // Act
        $result = $formatter->format([]);

        // Assert
        $this->assertSame('', $result);
    }

    public function testFormatsObjectAsKeyValuePairs(): void
    {
        // Arrange
        $formatter = new CsvFormatter();
        $obj = new class {
            public string $name = 'Arcanum';
            public int $version = 1;
        };

        // Act
        $lines = explode("\n", trim($formatter->format($obj)));

        // Assert
        $this->assertSame('key,value', $lines[0]);
        $this->assertSame('name,Arcanum', $lines[1]);
        $this->assertSame('version,1', $lines[2]);
    }

    public function testEscapesCommasInValues(): void
    {
        // Arrange
        $formatter = new CsvFormatter();

        // Act
        $lines = explode("\n", trim($formatter->format([['desc' => 'one, two, three']])));

        // Assert
        $this->assertSame('desc', $lines[0]);
        $this->assertSame('"one, two, three"', $lines[1]);
    }

    public function testEscapesQuotesInValues(): void
    {
        // Arrange
        $formatter = new CsvFormatter();

        // Act
        $lines = explode("\n", trim($formatter->format([['desc' => 'she said "hello"']])));

        // Assert
        $this->assertSame('desc', $lines[0]);
        $this->assertSame('"she said ""hello"""', $lines[1]);
    }

    public function testFormatsNestedValuesAsJson(): void
    {
        // Arrange
        $formatter = new CsvFormatter();

        // Act
        $lines = explode("\n", trim($formatter->format([
            ['name' => 'Alice', 'tags' => ['admin', 'user']],
        ])));

        // Assert
        $this->assertSame('name,tags', $lines[0]);
        $this->assertStringContainsString('Alice', $lines[1]);
        $this->assertStringContainsString('admin', $lines[1]);
    }

    public function testDtoClassParameterIsIgnored(): void
    {
        // Arrange
        $formatter = new CsvFormatter();

        // Act
        $result = $formatter->format([['a' => '1']], 'App\\Some\\Class');

        // Assert
        $this->assertStringContainsString('a', $result);
    }
}
