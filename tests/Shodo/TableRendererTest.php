<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\TableRenderer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(TableRenderer::class)]
final class TableRendererTest extends TestCase
{
    // ---------------------------------------------------------------
    // List of associative arrays (tabular)
    // ---------------------------------------------------------------

    public function testRendersListOfArraysAsTable(): void
    {
        // Arrange
        $renderer = new TableRenderer();
        $data = [
            ['id' => 1, 'name' => 'Jo'],
            ['id' => 2, 'name' => 'Sam'],
        ];

        // Act
        $result = $renderer->render($data);

        // Assert
        $this->assertStringContainsString('│ id │ name │', $result);
        $this->assertStringContainsString('│ 1  │ Jo   │', $result);
        $this->assertStringContainsString('│ 2  │ Sam  │', $result);
        $this->assertStringStartsWith('┌', $result);
        $this->assertStringEndsWith('┘', $result);
    }

    public function testRendersListOfObjectsAsTable(): void
    {
        // Arrange
        $renderer = new TableRenderer();
        $data = [
            (object) ['status' => 'ok', 'code' => 200],
            (object) ['status' => 'fail', 'code' => 500],
        ];

        // Act
        $result = $renderer->render($data);

        // Assert
        $this->assertStringContainsString('│ status │ code │', $result);
        $this->assertStringContainsString('│ ok     │ 200  │', $result);
        $this->assertStringContainsString('│ fail   │ 500  │', $result);
    }

    public function testTableHasCorrectBorderCharacters(): void
    {
        // Arrange
        $renderer = new TableRenderer();
        $data = [['a' => '1', 'b' => '2']];

        // Act
        $result = $renderer->render($data);

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

    public function testRendersAssociativeArrayAsKeyValueTable(): void
    {
        // Arrange
        $renderer = new TableRenderer();
        $data = ['status' => 'ok', 'version' => '1.0'];

        // Act
        $result = $renderer->render($data);

        // Assert
        $this->assertStringContainsString('│ key     │ value │', $result);
        $this->assertStringContainsString('│ status  │ ok    │', $result);
        $this->assertStringContainsString('│ version │ 1.0   │', $result);
    }

    // ---------------------------------------------------------------
    // List of scalars
    // ---------------------------------------------------------------

    public function testRendersListOfScalarsAsSingleColumnTable(): void
    {
        // Arrange
        $renderer = new TableRenderer();
        $data = ['alpha', 'beta', 'gamma'];

        // Act
        $result = $renderer->render($data);

        // Assert
        $this->assertStringContainsString('│ value │', $result);
        $this->assertStringContainsString('│ alpha │', $result);
        $this->assertStringContainsString('│ beta  │', $result);
    }

    // ---------------------------------------------------------------
    // Object input
    // ---------------------------------------------------------------

    public function testRendersSingleObjectAsKeyValueTable(): void
    {
        // Arrange
        $renderer = new TableRenderer();
        $data = (object) ['name' => 'Jo', 'age' => '30'];

        // Act
        $result = $renderer->render($data);

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
        $renderer = new TableRenderer();

        // Act & Assert
        $this->assertSame('', $renderer->render([]));
    }

    public function testReturnsEmptyStringForNull(): void
    {
        // Arrange
        $renderer = new TableRenderer();

        // Act & Assert
        $this->assertSame('', $renderer->render(null));
    }

    public function testHandlesBooleanValues(): void
    {
        // Arrange
        $renderer = new TableRenderer();
        $data = [['active' => true, 'deleted' => false]];

        // Act
        $result = $renderer->render($data);

        // Assert
        $this->assertStringContainsString('true', $result);
        $this->assertStringContainsString('false', $result);
    }

    public function testHandlesNestedArrayValues(): void
    {
        // Arrange
        $renderer = new TableRenderer();
        $data = [['name' => 'Jo', 'tags' => ['a', 'b']]];

        // Act
        $result = $renderer->render($data);

        // Assert
        $this->assertStringContainsString('["a","b"]', $result);
    }
}
