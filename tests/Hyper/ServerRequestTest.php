<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Hyper\ServerRequest;
use Arcanum\Hyper\Request;
use Arcanum\Hyper\Files\UploadedFile;
use Arcanum\Gather\Registry;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;

#[CoversClass(ServerRequest::class)]
#[UsesClass(Registry::class)]
#[UsesClass(\Arcanum\Hyper\Files\Error::class)]
#[UsesClass(\Arcanum\Hyper\Files\Normalizer::class)]
#[UsesClass(\Arcanum\Hyper\Files\UploadedFile::class)]
#[UsesClass(\Arcanum\Hyper\Files\UploadedFiles::class)]
final class ServerRequestTest extends TestCase
{
    public function testGetServerParams(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverParams->expects($this->once())
            ->method('toArray')
            ->willReturn([ 'HTTP_HOST' => 'example.com' ]);

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $data = $serverRequest->getServerParams();

        // Assert
        $this->assertSame([ 'HTTP_HOST' => 'example.com' ], $data);
    }

    public function testGetUploadedFilesDefaultEmpty(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\Stub */
        $request = $this->createStub(RequestInterface::class);

        /** @var Registry&\PHPUnit\Framework\MockObject\Stub */
        $serverParams = $this->createStub(Registry::class);

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $data = $serverRequest->getUploadedFiles();

        // Assert
        $this->assertEmpty($data);
    }

    public function testWithUploadedFiles(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\Stub */
        $request = $this->createStub(RequestInterface::class);

        /** @var Registry&\PHPUnit\Framework\MockObject\Stub */
        $serverParams = $this->createStub(Registry::class);

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->withUploadedFiles([
            'avatar' => [
                'tmp_name' => 'phpUxcOty',
                'name' => 'my-avatar.png',
                'size' => 90996,
                'type' => 'image/png',
                'error' => 0,
            ]
        ]);

        // Assert
        $this->assertInstanceOf(ServerRequest::class, $result);
        $this->assertInstanceOf(UploadedFile::class, $result->getUploadedFiles()['avatar']);
    }

    public function testGetCookieParams(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\Stub */
        $request = $this->createStub(RequestInterface::class);

        /** @var Registry&\PHPUnit\Framework\MockObject\Stub */
        $serverParams = $this->createStub(Registry::class);

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $data = $serverRequest->getCookieParams();

        // Assert
        $this->assertSame([], $data);
    }

    public function testWithCookieParams(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\Stub */
        $request = $this->createStub(RequestInterface::class);

        /** @var Registry&\PHPUnit\Framework\MockObject\Stub */
        $serverParams = $this->createStub(Registry::class);

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->withCookieParams([
            'name' => 'value'
        ]);

        // Assert
        $this->assertNotSame($serverRequest, $result);
        $this->assertSame([ 'name' => 'value' ], $result->getCookieParams());
    }

    public function testGetQueryParams(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\Stub */
        $request = $this->createStub(RequestInterface::class);

        /** @var Registry&\PHPUnit\Framework\MockObject\Stub */
        $serverParams = $this->createStub(Registry::class);

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $data = $serverRequest->getQueryParams();

        // Assert
        $this->assertSame([], $data);
    }

    public function testWithQueryParams(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\Stub */
        $request = $this->createStub(RequestInterface::class);

        /** @var Registry&\PHPUnit\Framework\MockObject\Stub */
        $serverParams = $this->createStub(Registry::class);

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->withQueryParams([
            'name' => 'value'
        ]);

        // Assert
        $this->assertNotSame($serverRequest, $result);
        $this->assertSame([ 'name' => 'value' ], $result->getQueryParams());
    }

    public function testGetAttributes(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\Stub */
        $request = $this->createStub(RequestInterface::class);

        /** @var Registry&\PHPUnit\Framework\MockObject\Stub */
        $serverParams = $this->createStub(Registry::class);

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $data = $serverRequest->getAttributes();

        // Assert
        $this->assertSame([], $data);
    }

    public function testGetAttribute(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\Stub */
        $request = $this->createStub(RequestInterface::class);

        /** @var Registry&\PHPUnit\Framework\MockObject\Stub */
        $serverParams = $this->createStub(Registry::class);

        $serverRequest = new ServerRequest($request, $serverParams);
        $serverRequest = $serverRequest->withAttribute('name', 'value');

        // Act
        $data = $serverRequest->getAttribute('name');

        // Assert
        $this->assertSame('value', $data);
    }

    public function testWithAttribute(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\Stub */
        $request = $this->createStub(RequestInterface::class);

        /** @var Registry&\PHPUnit\Framework\MockObject\Stub */
        $serverParams = $this->createStub(Registry::class);

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->withAttribute('name', 'value');

        // Assert
        $this->assertNotSame($serverRequest, $result);
        $this->assertSame([ 'name' => 'value' ], $result->getAttributes());
    }

    public function testWithoutAttribute(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\Stub */
        $request = $this->createStub(RequestInterface::class);

        /** @var Registry&\PHPUnit\Framework\MockObject\Stub */
        $serverParams = $this->createStub(Registry::class);

        $serverRequest = new ServerRequest($request, $serverParams);
        $serverRequest = $serverRequest->withAttribute('name', 'value');

        // Act
        $result = $serverRequest->withoutAttribute('name');

        // Assert
        $this->assertNotSame($serverRequest, $result);
        $this->assertSame([], $result->getAttributes());
    }

    public function testGetParsedBody(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\Stub */
        $request = $this->createStub(RequestInterface::class);

        /** @var Registry&\PHPUnit\Framework\MockObject\Stub */
        $serverParams = $this->createStub(Registry::class);

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $data = $serverRequest->getParsedBody();

        // Assert
        $this->assertNull($data);
    }

    public function testWithParsedBody(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\Stub */
        $request = $this->createStub(RequestInterface::class);

        $serverParams = $this->createStub(Registry::class);

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->withParsedBody([
            'name' => 'value'
        ]);

        // Assert
        $this->assertNotSame($serverRequest, $result);
        $this->assertSame([ 'name' => 'value' ], $result->getParsedBody());
    }

    public function testGetRequestTarget(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $request->expects($this->once())
            ->method('getRequestTarget')
            ->willReturn('/');

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $data = $serverRequest->getRequestTarget();

        // Assert
        $this->assertSame('/', $data);
    }

    public function testWithRequestTarget(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $newRequest = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $newRequest->expects($this->any())
            ->method('getRequestTarget')
            ->willReturn('/');

        $request->expects($this->once())
            ->method('withRequestTarget')
            ->with('/')
            ->willReturn($newRequest);

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->withRequestTarget('/');

        // Assert
        $this->assertNotSame($serverRequest, $result);
        $this->assertSame('/', $result->getRequestTarget());
    }

    public function testGetMethod(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $request->expects($this->once())
            ->method('getMethod')
            ->willReturn('POST');

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $data = $serverRequest->getMethod();

        // Assert
        $this->assertSame('POST', $data);
    }

    public function testWithMethod(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $newRequest = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $newRequest->expects($this->any())
            ->method('getMethod')
            ->willReturn('DELETE');

        $request->expects($this->once())
            ->method('withMethod')
            ->with('DELETE')
            ->willReturn($newRequest);

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->withMethod('DELETE');

        // Assert
        $this->assertNotSame($serverRequest, $result);
        $this->assertSame('DELETE', $result->getMethod());
    }

    public function testGetUri(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var UriInterface&\PHPUnit\Framework\MockObject\MockObject */
        $uri = $this->getMockBuilder(UriInterface::class)
            ->getMock();

        $request->expects($this->once())
            ->method('getUri')
            ->willReturn($uri);

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $data = $serverRequest->getUri();

        // Assert
        $this->assertSame($uri, $data);
    }

    public function testWithUri(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $newRequest = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var UriInterface&\PHPUnit\Framework\MockObject\MockObject */
        $uri = $this->getMockBuilder(UriInterface::class)
            ->getMock();

        $newRequest->expects($this->any())
            ->method('getUri')
            ->willReturn($uri);

        $request->expects($this->once())
            ->method('withUri')
            ->with($uri, true)
            ->willReturn($newRequest);

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->withUri($uri, true);

        // Assert
        $this->assertNotSame($serverRequest, $result);
        $this->assertSame($uri, $result->getUri());
    }

    public function testGetProtocolVersion(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $request->expects($this->once())
            ->method('getProtocolVersion')
            ->willReturn('1.1');

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $data = $serverRequest->getProtocolVersion();

        // Assert
        $this->assertSame('1.1', $data);
    }

    public function testWithProtocolVersion(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $newRequest = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $newRequest->expects($this->any())
            ->method('getProtocolVersion')
            ->willReturn('1.0');

        $request->expects($this->once())
            ->method('withProtocolVersion')
            ->with('1.0')
            ->willReturn($newRequest);

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->withProtocolVersion('1.0');

        // Assert
        $this->assertNotSame($serverRequest, $result);
        $this->assertSame('1.0', $result->getProtocolVersion());
    }

    public function testGetHeaders(): void
    {
        // Arrange
        $headers = [
            'Content-Type' => ['application/json'],
            'Content-Length' => ['100'],
        ];

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $request->expects($this->once())
            ->method('getHeaders')
            ->willReturn($headers);

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $data = $serverRequest->getHeaders();

        // Assert
        $this->assertSame($headers, $data);
    }

    public function testGetHeader(): void
    {
        // Arrange
        $headers = [
            'Content-Type' => ['application/json'],
        ];

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $request->expects($this->once())
            ->method('getHeader')
            ->with('Content-Type')
            ->willReturn($headers['Content-Type']);

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $data = $serverRequest->getHeader('Content-Type');

        // Assert
        $this->assertSame(['application/json'], $data);
    }

    public function testGetHeaderLine(): void
    {
        // Arrange
        $headers = [
            'Content-Type' => ['application/json', 'application/xml'],
        ];

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $request->expects($this->once())
            ->method('getHeaderLine')
            ->with('Content-Type')
            ->willReturn(implode(',', $headers['Content-Type']));

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $data = $serverRequest->getHeaderLine('Content-Type');

        // Assert
        $this->assertSame('application/json,application/xml', $data);
    }

    public function testHasHeader(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $request->expects($this->exactly(2))
            ->method('hasHeader')
            ->willReturnOnConsecutiveCalls(
                true,
                false
            );

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result1 = $serverRequest->hasHeader('Content-Type');
        $result2 = $serverRequest->hasHeader('Content-Length');

        // Assert
        $this->assertTrue($result1);
        $this->assertFalse($result2);
    }

    public function testWithHeader(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $newRequest = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $newRequest->expects($this->any())
            ->method('getHeaders')
            ->willReturn([
                'Content-Type' => ['application/json'],
            ]);

        $request->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($newRequest);

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->withHeader('Content-Type', 'application/json');

        // Assert
        $this->assertNotSame($serverRequest, $result);
        $this->assertSame(['Content-Type' => ['application/json']], $result->getHeaders());
    }

    public function testWithAddedHeader(): void
    {
        // Arrange
        $newHeaders = [
            'Content-Type' => ['application/json', 'application/xml'],
        ];

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $newRequest = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $newRequest->expects($this->any())
            ->method('getHeaders')
            ->willReturn($newHeaders);

        $request->expects($this->once())
            ->method('withAddedHeader')
            ->with('Content-Type', 'application/xml')
            ->willReturn($newRequest);

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->withAddedHeader('Content-Type', 'application/xml');

        // Assert
        $this->assertNotSame($serverRequest, $result);
        $this->assertSame($newHeaders, $result->getHeaders());
    }

    public function testWithoutHeader(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $newRequest = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $newRequest->expects($this->any())
            ->method('getHeaders')
            ->willReturn([]);

        $request->expects($this->once())
            ->method('withoutHeader')
            ->with('Content-Type')
            ->willReturn($newRequest);

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->withoutHeader('Content-Type');

        // Assert
        $this->assertNotSame($serverRequest, $result);
        $this->assertSame([], $result->getHeaders());
    }

    public function testGetBody(): void
    {
        // Arrange
        $body = $this->createStub(StreamInterface::class);

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $request->expects($this->once())
            ->method('getBody')
            ->willReturn($body);

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->getBody();

        // Assert
        $this->assertSame($body, $result);
    }

    public function testWithBody(): void
    {
        // Arrange
        $body = $this->createStub(StreamInterface::class);

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $newRequest = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $newRequest->expects($this->any())
            ->method('getBody')
            ->willReturn($body);

        $request->expects($this->once())
            ->method('withBody')
            ->with($body)
            ->willReturn($newRequest);

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = $serverRequest->withBody($body);

        // Assert
        $this->assertNotSame($serverRequest, $result);
        $this->assertSame($body, $result->getBody());
    }

    public function testToString(): void
    {
        // Arrange
        $body = $this->getMockBuilder(StreamInterface::class)
            ->getMock();

        $body->expects($this->once())
            ->method('getContents')
            ->willReturn('body text');

        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(RequestInterface::class)
            ->getMock();

        $request->expects($this->once())
            ->method('getBody')
            ->willReturn($body);

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = (string) $serverRequest;

        // Assert
        $this->assertSame('body text', $result);
    }

    public function testToStringWithStringableRequestChild(): void
    {
        // Arrange
        /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();

        $request->expects($this->once())
            ->method('__toString')
            ->willReturn('body text');

        /** @var Registry&\PHPUnit\Framework\MockObject\MockObject */
        $serverParams = $this->getMockBuilder(Registry::class)
            ->getMock();

        $serverRequest = new ServerRequest($request, $serverParams);

        // Act
        $result = (string) $serverRequest;

        // Assert
        $this->assertSame('body text', $result);
    }
}
