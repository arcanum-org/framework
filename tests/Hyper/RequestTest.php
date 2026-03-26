<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Hyper\Request;
use Arcanum\Hyper\RequestMethod;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Request::class)]
#[UsesClass(RequestMethod::class)]
final class RequestTest extends TestCase
{
    public function testGetRequestTarget(): void
    {
        // Arrange
        $message = $this->createStub(MessageInterface::class);
        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $requestTarget = $request->getRequestTarget();

        // Assert
        $this->assertSame('/', $requestTarget);
    }

    public function testNewRequestMessageHasHostWithPort(): void
    {
        // Arrange
        /** @var MessageInterface&\PHPUnit\Framework\MockObject\MockObject $message */
        $message = $this->getMockBuilder(MessageInterface::class)
            ->getMock();

        $message->expects($this->once())
            ->method('hasHeader')
            ->with('Host')
            ->willReturn(true);

        $message->expects($this->once())
            ->method('withHeader')
            ->with('Host', 'example.com:9090')
            ->willReturnSelf();

        /** @var UriInterface&\PHPUnit\Framework\MockObject\MockObject $uri */
        $uri = $this->getMockBuilder(UriInterface::class)
            ->getMock();

        $uri->expects($this->once())
            ->method('getHost')
            ->willReturn('example.com');

        $uri->expects($this->once())
            ->method('getPort')
            ->willReturn(9090);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $requestTarget = $request->getRequestTarget();

        // Assert
        $this->assertSame('/', $requestTarget);
    }

    public function testGetRequestTargetIncludesQueryString(): void
    {
        // Arrange
        $message = $this->createStub(MessageInterface::class);

        /** @var UriInterface&\PHPUnit\Framework\MockObject\MockObject $uri */
        $uri = $this->getMockBuilder(UriInterface::class)
            ->getMock();

        $uri->expects($this->once())
            ->method('getQuery')
            ->willReturn('foo=bar');

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $requestTarget = $request->getRequestTarget();

        // Assert
        $this->assertSame('/?foo=bar', $requestTarget);
    }

    public function testWithRequestTarget(): void
    {
        // Arrange
        $message = $this->createStub(MessageInterface::class);
        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $newRequest = $request->withRequestTarget('/test');

        // Assert
        $this->assertNotSame($request, $newRequest);
        $this->assertSame('/test', $newRequest->getRequestTarget());
    }

    public function testWithRequestTargetThrowsInvalidArgumentExceptionIfTargetContainsSpace(): void
    {
        // Arrange
        $message = $this->createStub(MessageInterface::class);
        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $request->withRequestTarget('/test test');
    }

    public function testGetMethod(): void
    {
        // Arrange
        $message = $this->createStub(MessageInterface::class);
        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $method = $request->getMethod();

        // Assert
        $this->assertSame(RequestMethod::GET->value, $method);
    }

    public function testWithMethod(): void
    {
        // Arrange
        $message = $this->createStub(MessageInterface::class);
        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $newRequest = $request->withMethod(RequestMethod::POST->value);

        // Assert
        $this->assertNotSame($request, $newRequest);
        $this->assertSame(RequestMethod::POST->value, $newRequest->getMethod());
    }

    public function testGetUri(): void
    {
        // Arrange
        $message = $this->createStub(MessageInterface::class);
        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $uri = $request->getUri();

        // Assert
        $this->assertSame($uri, $uri);
    }

    public function testWithUri(): void
    {
        // Arrange
        $message = $this->createStub(MessageInterface::class);
        $uri = $this->createStub(UriInterface::class);
        $newUri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $newRequest = $request->withUri($newUri);

        // Assert
        $this->assertNotSame($request, $newRequest);
        $this->assertSame($newUri, $newRequest->getUri());
    }

    public function testWithUriChangesNothingIfUriIsTheSameInstance(): void
    {
        // Arrange
        $message = $this->createStub(MessageInterface::class);
        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $newRequest = $request->withUri($uri);

        // Assert
        $this->assertSame($request, $newRequest);
    }

    public function testGetProtocolVersionDelegatesToMessage(): void
    {
        // Arrange
        $protocolVersion = '1.1';

        /** @var MessageInterface&\PHPUnit\Framework\MockObject\MockObject $message */
        $message = $this->getMockBuilder(MessageInterface::class)
                        ->getMock();

        $message->expects($this->once())
                ->method('getProtocolVersion')
                ->willReturn($protocolVersion);

        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $result = $request->getProtocolVersion();

        // Assert
        $this->assertSame($protocolVersion, $result);
    }

    public function testWithProtocolVersion(): void
    {
        // Arrange
        $protocolVersion = '1.1';

        /** @var MessageInterface&\PHPUnit\Framework\MockObject\MockObject $message */
        $message = $this->getMockBuilder(MessageInterface::class)
            ->getMock();

        $newMessage = $this->createStub(MessageInterface::class);

        $newMessage->method('getProtocolVersion')
            ->willReturn($protocolVersion);

        $message->expects($this->once())
            ->method('withProtocolVersion')
            ->with($protocolVersion)
            ->willReturn($newMessage);

        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $result = $request->withProtocolVersion($protocolVersion);

        // Assert
        $this->assertNotSame($request, $result);
        $this->assertSame($protocolVersion, $result->getProtocolVersion());
    }

    public function testGetHeaders(): void
    {
        // Arrange
        $headers = [
            'Content-Type' => ['application/json'],
            'X-Test' => ['test'],
        ];

        /** @var MessageInterface&\PHPUnit\Framework\MockObject\MockObject $message */
        $message = $this->getMockBuilder(MessageInterface::class)
                        ->getMock();

        $message->expects($this->once())
                ->method('getHeaders')
                ->willReturn($headers);

        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $result = $request->getHeaders();

        // Assert
        $this->assertSame($headers, $result);
    }

    public function testHasHeader(): void
    {
        // Arrange
        /** @var MessageInterface&\PHPUnit\Framework\MockObject\MockObject $message */
        $message = $this->getMockBuilder(MessageInterface::class)
            ->getMock();

        $message->expects($this->exactly(2))
            ->method('hasHeader')
            ->willReturnCallback(fn(string $header) => match ($header) {
                'Host' => false,
                'Content-Type' => true,
                default => false,
            });


        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $result = $request->hasHeader('Content-Type');

        // Assert
        $this->assertTrue($result);
    }

    public function testGetHeader(): void
    {
        // Arrange
        $header = ['application/json'];

        /** @var MessageInterface&\PHPUnit\Framework\MockObject\MockObject $message */
        $message = $this->getMockBuilder(MessageInterface::class)
                        ->getMock();

        $message->expects($this->once())
                ->method('getHeader')
                ->with('Content-Type')
                ->willReturn($header);

        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $result = $request->getHeader('Content-Type');

        // Assert
        $this->assertSame($header, $result);
    }

    public function testGetHeaderLine(): void
    {
        // Arrange
        $header = ['application/json', 'foo'];

        /** @var MessageInterface&\PHPUnit\Framework\MockObject\MockObject $message */
        $message = $this->getMockBuilder(MessageInterface::class)
                        ->getMock();

        $message->expects($this->once())
                ->method('getHeaderLine')
                ->with('Content-Type')
                ->willReturn(implode(',', $header));

        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $result = $request->getHeaderLine('Content-Type');

        // Assert
        $this->assertSame(implode(',', $header), $result);
    }

    public function testWithHeader(): void
    {
        // Arrange
        $header = ['application/json'];
        $newHeader = ['application/xml'];

        /** @var MessageInterface&\PHPUnit\Framework\MockObject\MockObject $message */
        $message = $this->getMockBuilder(MessageInterface::class)
            ->getMock();

        $newMessage = $this->createStub(MessageInterface::class);

        $newMessage->method('getHeader')
            ->willReturn($newHeader);

        $message->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', $header)
            ->willReturn($newMessage);

        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $result = $request->withHeader('Content-Type', $header);

        // Assert
        $this->assertNotSame($request, $result);
        $this->assertSame($newHeader, $result->getHeader('Content-Type'));
    }

    public function testWithAddedHeader(): void
    {
        // Arrange
        $header = ['application/json'];
        $newHeader = ['application/xml'];

        /** @var MessageInterface&\PHPUnit\Framework\MockObject\MockObject $message */
        $message = $this->getMockBuilder(MessageInterface::class)
            ->getMock();

        $newMessage = $this->createStub(MessageInterface::class);

        $newMessage->method('getHeader')
            ->willReturn($header + $newHeader);

        $message->expects($this->once())
            ->method('withAddedHeader')
            ->with('Content-Type', $newHeader)
            ->willReturn($newMessage);

        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $result = $request->withAddedHeader('Content-Type', $newHeader);

        // Assert
        $this->assertNotSame($request, $result);
        $this->assertSame($header + $newHeader, $result->getHeader('Content-Type'));
    }

    public function testWithoutHeader(): void
    {
        // Arrange
        $header = ['application/json'];

        /** @var MessageInterface&\PHPUnit\Framework\MockObject\MockObject $message */
        $message = $this->getMockBuilder(MessageInterface::class)
            ->getMock();

        $newMessage = $this->createStub(MessageInterface::class);

        $newMessage->method('getHeader')
            ->willReturn([]);

        $message->expects($this->once())
            ->method('withoutHeader')
            ->with('Content-Type')
            ->willReturn($newMessage);

        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $result = $request->withoutHeader('Content-Type');

        // Assert
        $this->assertNotSame($request, $result);
        $this->assertSame([], $result->getHeader('Content-Type'));
    }

    public function testGetBody(): void
    {
        // Arrange
        $body = $this->createStub(StreamInterface::class);

        /** @var MessageInterface&\PHPUnit\Framework\MockObject\MockObject $message */
        $message = $this->getMockBuilder(MessageInterface::class)
                        ->getMock();

        $message->expects($this->once())
                ->method('getBody')
                ->willReturn($body);

        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $result = $request->getBody();

        // Assert
        $this->assertSame($body, $result);
    }

    public function testWithBody(): void
    {
        // Arrange
        $body = $this->createStub(StreamInterface::class);

        /** @var MessageInterface&\PHPUnit\Framework\MockObject\MockObject $message */
        $message = $this->getMockBuilder(MessageInterface::class)
            ->getMock();

        $newMessage = $this->createStub(MessageInterface::class);

        $newMessage->method('getBody')
            ->willReturn($body);

        $message->expects($this->once())
            ->method('withBody')
            ->with($body)
            ->willReturn($newMessage);

        $uri = $this->createStub(UriInterface::class);

        $request = new Request($message, RequestMethod::GET, $uri);

        // Act
        $result = $request->withBody($body);

        // Assert
        $this->assertNotSame($request, $result);
        $this->assertSame($body, $result->getBody());
    }
}
