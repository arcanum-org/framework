<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\CliRenderer;
use Arcanum\Shodo\TableRenderer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CliRenderer::class)]
#[UsesClass(TableRenderer::class)]
final class CliRendererTest extends TestCase
{
    // ---------------------------------------------------------------
    // Associative array → key-value pairs
    // ---------------------------------------------------------------

    public function testRendersAssociativeArrayAsKeyValuePairs(): void
    {
        // Arrange
        $renderer = new CliRenderer();

        // Act
        $result = $renderer->render(['status' => 'ok', 'version' => '1.0']);

        // Assert
        $this->assertStringContainsString('status', $result);
        $this->assertStringContainsString('ok', $result);
        $this->assertStringContainsString('version', $result);
        $this->assertStringContainsString('1.0', $result);
    }

    public function testKeyValuePairsAreAligned(): void
    {
        // Arrange
        $renderer = new CliRenderer();

        // Act
        $result = $renderer->render(['a' => '1', 'long_key' => '2']);

        // Assert — both values should be at the same column
        $lines = explode(\PHP_EOL, $result);
        $pos1 = strpos($lines[0], '1');
        $pos2 = strpos($lines[1], '2');
        $this->assertSame($pos1, $pos2);
    }

    // ---------------------------------------------------------------
    // Object → key-value pairs
    // ---------------------------------------------------------------

    public function testRendersObjectAsKeyValuePairs(): void
    {
        // Arrange
        $renderer = new CliRenderer();
        $data = (object) ['name' => 'Jo', 'email' => 'jo@test.com'];

        // Act
        $result = $renderer->render($data);

        // Assert
        $this->assertStringContainsString('name', $result);
        $this->assertStringContainsString('Jo', $result);
        $this->assertStringContainsString('email', $result);
    }

    // ---------------------------------------------------------------
    // List of arrays → table (delegates to TableRenderer)
    // ---------------------------------------------------------------

    public function testRendersListOfArraysAsTable(): void
    {
        // Arrange
        $renderer = new CliRenderer();
        $data = [
            ['id' => 1, 'name' => 'Jo'],
            ['id' => 2, 'name' => 'Sam'],
        ];

        // Act
        $result = $renderer->render($data);

        // Assert — should have table borders
        $this->assertStringContainsString('┌', $result);
        $this->assertStringContainsString('│', $result);
        $this->assertStringContainsString('Jo', $result);
    }

    public function testRendersListOfObjectsAsTable(): void
    {
        // Arrange
        $renderer = new CliRenderer();
        $data = [
            (object) ['x' => 1],
            (object) ['x' => 2],
        ];

        // Act
        $result = $renderer->render($data);

        // Assert
        $this->assertStringContainsString('┌', $result);
    }

    // ---------------------------------------------------------------
    // Scalar → plain text
    // ---------------------------------------------------------------

    public function testRendersStringScalar(): void
    {
        // Arrange
        $renderer = new CliRenderer();

        // Act & Assert
        $this->assertSame('hello', $renderer->render('hello'));
    }

    public function testRendersIntegerScalar(): void
    {
        // Arrange
        $renderer = new CliRenderer();

        // Act & Assert
        $this->assertSame('42', $renderer->render(42));
    }

    public function testRendersBooleanTrue(): void
    {
        // Arrange
        $renderer = new CliRenderer();

        // Act & Assert
        $this->assertSame('1', $renderer->render(true));
    }

    // ---------------------------------------------------------------
    // Empty / null
    // ---------------------------------------------------------------

    public function testRendersNullAsEmptyString(): void
    {
        // Arrange
        $renderer = new CliRenderer();

        // Act & Assert
        $this->assertSame('', $renderer->render(null));
    }

    public function testRendersEmptyArrayAsEmptyString(): void
    {
        // Arrange
        $renderer = new CliRenderer();

        // Act & Assert
        $this->assertSame('', $renderer->render([]));
    }

    // ---------------------------------------------------------------
    // Nested values in key-value mode
    // ---------------------------------------------------------------

    public function testNestedArrayInKeyValueRenderedAsJson(): void
    {
        // Arrange
        $renderer = new CliRenderer();

        // Act
        $result = $renderer->render(['tags' => ['a', 'b']]);

        // Assert
        $this->assertStringContainsString('["a","b"]', $result);
    }
}
