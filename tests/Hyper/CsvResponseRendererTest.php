<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Flow\River\StreamResource;
use Arcanum\Gather\IgnoreCaseRegistry;
use Arcanum\Gather\Registry;
use Arcanum\Hyper\CsvResponseRenderer;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Arcanum\Shodo\CsvFormatter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(CsvResponseRenderer::class)]
#[UsesClass(CsvFormatter::class)]
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
final class CsvResponseRendererTest extends TestCase
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
        $renderer = new CsvResponseRenderer();

        // Act
        $response = $renderer->render([['name' => 'Alice']]);

        // Assert
        $this->assertInstanceOf(ResponseInterface::class, $response);
    }

    public function testRenderSetsCsvContentType(): void
    {
        // Arrange
        $renderer = new CsvResponseRenderer();

        // Act
        $response = $renderer->render([['name' => 'Alice']]);

        // Assert
        $this->assertSame('text/csv; charset=UTF-8', $response->getHeaderLine('Content-Type'));
    }

    public function testRenderSetsContentLength(): void
    {
        // Arrange
        $renderer = new CsvResponseRenderer();

        // Act
        $response = $renderer->render([['name' => 'Alice']]);
        $body = $this->readBody($response);

        // Assert
        $this->assertSame((string) strlen($body), $response->getHeaderLine('Content-Length'));
    }

    public function testRenderReturns200Status(): void
    {
        // Arrange
        $renderer = new CsvResponseRenderer();

        // Act
        $response = $renderer->render([['name' => 'Alice']]);

        // Assert
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testRendersTabularData(): void
    {
        // Arrange
        $renderer = new CsvResponseRenderer();
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

    public function testRendersEmptyArray(): void
    {
        // Arrange
        $renderer = new CsvResponseRenderer();

        // Act
        $body = $this->readBody($renderer->render([]));

        // Assert
        $this->assertSame('', $body);
    }

    public function testBodyMatchesFormatterOutput(): void
    {
        // Arrange
        $formatter = new CsvFormatter();
        $renderer = new CsvResponseRenderer($formatter);
        $data = [['x' => 1, 'y' => 2]];

        // Act
        $expected = $formatter->format($data);
        $body = $this->readBody($renderer->render($data));

        // Assert
        $this->assertSame($expected, $body);
    }
}
