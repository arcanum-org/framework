<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Flow\River\StreamResource;
use Arcanum\Gather\IgnoreCaseRegistry;
use Arcanum\Gather\Registry;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Arcanum\Shodo\CsvRenderer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(CsvRenderer::class)]
#[UsesClass(Response::class)]
#[UsesClass(Message::class)]
#[UsesClass(Headers::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Version::class)]
#[UsesClass(Stream::class)]
#[UsesClass(LazyResource::class)]
#[UsesClass(StreamResource::class)]
#[UsesClass(IgnoreCaseRegistry::class)]
#[UsesClass(Registry::class)]
final class CsvRendererTest extends TestCase
{
    private function readBody(ResponseInterface $response): string
    {
        $body = $response->getBody();
        $body->rewind();
        return $body->getContents();
    }

    public function testRenderReturnsResponseInterface(): void
    {
        // Arrange
        $renderer = new CsvRenderer();

        // Act
        $response = $renderer->render([['name' => 'Alice']]);

        // Assert
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testRenderSetsCsvContentType(): void
    {
        // Arrange
        $renderer = new CsvRenderer();

        // Act
        $response = $renderer->render([['name' => 'Alice']]);

        // Assert
        $this->assertSame('text/csv; charset=UTF-8', $response->getHeaderLine('Content-Type'));
    }

    public function testRenderSetsContentLength(): void
    {
        // Arrange
        $renderer = new CsvRenderer();

        // Act
        $response = $renderer->render([['name' => 'Alice']]);
        $body = $this->readBody($response);

        // Assert
        $this->assertSame((string) strlen($body), $response->getHeaderLine('Content-Length'));
    }

    public function testRenderReturns200Status(): void
    {
        // Arrange
        $renderer = new CsvRenderer();

        // Act
        $response = $renderer->render([['name' => 'Alice']]);

        // Assert
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRendersTabularData(): void
    {
        // Arrange
        $renderer = new CsvRenderer();
        $data = [
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ];

        // Act
        $body = $this->readBody($renderer->render($data));
        $lines = explode("\n", trim($body));

        // Assert
        $this->assertSame('name,age', $lines[0]);
        $this->assertSame('Alice,30', $lines[1]);
        $this->assertSame('Bob,25', $lines[2]);
    }

    public function testRendersAssociativeArrayAsKeyValuePairs(): void
    {
        // Arrange
        $renderer = new CsvRenderer();

        // Act
        $body = $this->readBody($renderer->render(['status' => 'ok', 'version' => '1.0']));
        $lines = explode("\n", trim($body));

        // Assert
        $this->assertSame('key,value', $lines[0]);
        $this->assertSame('status,ok', $lines[1]);
        $this->assertSame('version,1.0', $lines[2]);
    }

    public function testRendersSequentialScalarArray(): void
    {
        // Arrange
        $renderer = new CsvRenderer();

        // Act
        $body = $this->readBody($renderer->render(['apple', 'banana', 'cherry']));
        $lines = explode("\n", trim($body));

        // Assert
        $this->assertSame('value', $lines[0]);
        $this->assertSame('apple', $lines[1]);
        $this->assertSame('banana', $lines[2]);
        $this->assertSame('cherry', $lines[3]);
    }

    public function testRendersScalarValue(): void
    {
        // Arrange
        $renderer = new CsvRenderer();

        // Act
        $body = $this->readBody($renderer->render('hello'));
        $lines = explode("\n", trim($body));

        // Assert
        $this->assertSame('value', $lines[0]);
        $this->assertSame('hello', $lines[1]);
    }

    public function testRendersEmptyArray(): void
    {
        // Arrange
        $renderer = new CsvRenderer();

        // Act
        $body = $this->readBody($renderer->render([]));

        // Assert
        $this->assertSame('', $body);
    }

    public function testRendersObjectAsKeyValuePairs(): void
    {
        // Arrange
        $renderer = new CsvRenderer();
        $obj = new class {
            public string $name = 'Arcanum';
            public int $version = 1;
        };

        // Act
        $body = $this->readBody($renderer->render($obj));
        $lines = explode("\n", trim($body));

        // Assert
        $this->assertSame('key,value', $lines[0]);
        $this->assertSame('name,Arcanum', $lines[1]);
        $this->assertSame('version,1', $lines[2]);
    }

    public function testEscapesCommasInValues(): void
    {
        // Arrange
        $renderer = new CsvRenderer();

        // Act
        $body = $this->readBody($renderer->render([['desc' => 'one, two, three']]));
        $lines = explode("\n", trim($body));

        // Assert
        $this->assertSame('desc', $lines[0]);
        $this->assertSame('"one, two, three"', $lines[1]);
    }

    public function testEscapesQuotesInValues(): void
    {
        // Arrange
        $renderer = new CsvRenderer();

        // Act
        $body = $this->readBody($renderer->render([['desc' => 'she said "hello"']]));
        $lines = explode("\n", trim($body));

        // Assert
        $this->assertSame('desc', $lines[0]);
        $this->assertSame('"she said ""hello"""', $lines[1]);
    }

    public function testRendersNestedValuesAsJson(): void
    {
        // Arrange
        $renderer = new CsvRenderer();

        // Act
        $body = $this->readBody($renderer->render([
            ['name' => 'Alice', 'tags' => ['admin', 'user']],
        ]));
        $lines = explode("\n", trim($body));

        // Assert
        $this->assertSame('name,tags', $lines[0]);
        // Nested array serialized as JSON within the CSV cell
        $this->assertStringContainsString('Alice', $lines[1]);
        $this->assertStringContainsString('admin', $lines[1]);
    }

    public function testDtoClassParameterIsIgnored(): void
    {
        // Arrange
        $renderer = new CsvRenderer();

        // Act
        $response = $renderer->render([['a' => '1']], 'App\\Some\\Class');

        // Assert
        $this->assertSame(200, $response->getStatusCode());
    }
}
