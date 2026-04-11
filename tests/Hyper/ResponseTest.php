<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Flow\River\EmptyStream;
use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Gather\IgnoreCaseRegistry;
use Arcanum\Gather\Registry;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Phrase;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\StreamInterface;

#[CoversClass(Response::class)]
#[UsesClass(Headers::class)]
#[UsesClass(Message::class)]
#[UsesClass(EmptyStream::class)]
#[UsesClass(Stream::class)]
#[UsesClass(LazyResource::class)]
#[UsesClass(IgnoreCaseRegistry::class)]
#[UsesClass(Registry::class)]
final class ResponseTest extends TestCase
{
    private function emptyResponse(StatusCode $status = StatusCode::OK): Response
    {
        return new Response(
            new Message(new Headers([]), new EmptyStream(), Version::v11),
            $status,
        );
    }

    // ---------------------------------------------------------------
    // Status code
    // ---------------------------------------------------------------

    public function testGetStatusCode(): void
    {
        // Arrange & Act
        $response = $this->emptyResponse(StatusCode::OK);

        // Assert
        $this->assertSame(200, $response->getStatusCode());
    }

    public function testGetStatusCodeNotFound(): void
    {
        // Arrange & Act
        $response = $this->emptyResponse(StatusCode::NotFound);

        // Assert
        $this->assertSame(404, $response->getStatusCode());
    }

    public function testWithStatusReturnsNewInstance(): void
    {
        // Arrange
        $original = $this->emptyResponse(StatusCode::OK);

        // Act
        $modified = $original->withStatus(404);

        // Assert
        $this->assertSame(200, $original->getStatusCode());
        $this->assertSame(404, $modified->getStatusCode());
        $this->assertNotSame($original, $modified);
    }

    // ---------------------------------------------------------------
    // Reason phrase
    // ---------------------------------------------------------------

    public function testGetReasonPhraseDefaultsFromStatusCode(): void
    {
        // Arrange & Act
        $response = $this->emptyResponse(StatusCode::OK);

        // Assert
        $this->assertSame('OK', $response->getReasonPhrase());
    }

    public function testGetReasonPhraseForNotFound(): void
    {
        // Arrange & Act
        $response = $this->emptyResponse(StatusCode::NotFound);

        // Assert
        $this->assertSame('Not Found', $response->getReasonPhrase());
    }

    public function testWithStatusSetsReasonPhrase(): void
    {
        // Arrange
        $response = $this->emptyResponse();

        // Act
        $modified = $response->withStatus(200, 'All Good');

        // Assert — 'All Good' is not a standard phrase, so Phrase::tryFrom returns null
        // and the default reason for 200 is used
        $this->assertSame('OK', $modified->getReasonPhrase());
    }

    // ---------------------------------------------------------------
    // Character set
    // ---------------------------------------------------------------

    public function testGetCharacterSetDefaultsToUtf8(): void
    {
        // Arrange & Act
        $response = $this->emptyResponse();

        // Assert
        $this->assertSame('UTF-8', $response->getCharacterSet());
    }

    // ---------------------------------------------------------------
    // Headers (delegated to Message)
    // ---------------------------------------------------------------

    public function testGetHeaders(): void
    {
        // Arrange
        $response = new Response(
            new Message(
                new Headers(['Content-Type' => ['application/json']]),
                new EmptyStream(),
                Version::v11,
            ),
        );

        // Act & Assert
        $this->assertSame(['Content-Type' => ['application/json']], $response->getHeaders());
    }

    public function testHasHeader(): void
    {
        // Arrange
        $response = new Response(
            new Message(
                new Headers(['Content-Type' => ['application/json']]),
                new EmptyStream(),
                Version::v11,
            ),
        );

        // Act & Assert
        $this->assertTrue($response->hasHeader('Content-Type'));
        $this->assertFalse($response->hasHeader('X-Custom'));
    }

    public function testGetHeader(): void
    {
        // Arrange
        $response = new Response(
            new Message(
                new Headers(['Content-Type' => ['application/json']]),
                new EmptyStream(),
                Version::v11,
            ),
        );

        // Act & Assert
        $this->assertSame(['application/json'], $response->getHeader('Content-Type'));
    }

    public function testGetHeaderLine(): void
    {
        // Arrange
        $response = new Response(
            new Message(
                new Headers(['Accept' => ['text/html', 'application/json']]),
                new EmptyStream(),
                Version::v11,
            ),
        );

        // Act & Assert
        $this->assertSame('text/html,application/json', $response->getHeaderLine('Accept'));
    }

    public function testWithHeaderReturnsNewInstance(): void
    {
        // Arrange
        $original = $this->emptyResponse();

        // Act
        $modified = $original->withHeader('X-Custom', 'value');

        // Assert
        $this->assertFalse($original->hasHeader('X-Custom'));
        $this->assertSame(['value'], $modified->getHeader('X-Custom'));
    }

    public function testWithAddedHeaderReturnsNewInstance(): void
    {
        // Arrange
        $response = new Response(
            new Message(
                new Headers(['Accept' => ['text/html']]),
                new EmptyStream(),
                Version::v11,
            ),
        );

        // Act
        $modified = $response->withAddedHeader('Accept', 'application/json');

        // Assert
        $this->assertSame(['text/html'], $response->getHeader('Accept'));
        $this->assertSame(['text/html', 'application/json'], $modified->getHeader('Accept'));
    }

    public function testWithoutHeaderReturnsNewInstance(): void
    {
        // Arrange
        $response = new Response(
            new Message(
                new Headers(['Content-Type' => ['application/json']]),
                new EmptyStream(),
                Version::v11,
            ),
        );

        // Act
        $modified = $response->withoutHeader('Content-Type');

        // Assert
        $this->assertTrue($response->hasHeader('Content-Type'));
        $this->assertFalse($modified->hasHeader('Content-Type'));
    }

    // ---------------------------------------------------------------
    // Body (delegated to Message)
    // ---------------------------------------------------------------

    public function testGetBody(): void
    {
        // Arrange
        $body = new Stream(LazyResource::for('php://memory', 'w+'));
        $body->write('hello');
        $response = new Response(
            new Message(new Headers([]), $body, Version::v11),
        );

        // Act
        $result = $response->getBody();

        // Assert
        $this->assertSame($body, $result);
    }

    public function testWithBodyReturnsNewInstance(): void
    {
        // Arrange
        $original = $this->emptyResponse();
        $newBody = new Stream(LazyResource::for('php://memory', 'w+'));

        // Act
        $modified = $original->withBody($newBody);

        // Assert
        $this->assertNotSame($original->getBody(), $modified->getBody());
    }

    // ---------------------------------------------------------------
    // Protocol version (delegated to Message)
    // ---------------------------------------------------------------

    public function testGetProtocolVersion(): void
    {
        // Arrange & Act
        $response = $this->emptyResponse();

        // Assert
        $this->assertSame('1.1', $response->getProtocolVersion());
    }

    public function testWithProtocolVersionReturnsNewInstance(): void
    {
        // Arrange
        $original = $this->emptyResponse();

        // Act
        $modified = $original->withProtocolVersion('2.0');

        // Assert
        $this->assertSame('1.1', $original->getProtocolVersion());
        $this->assertSame('2.0', $modified->getProtocolVersion());
    }
}
