<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Psr\Http\Message\ResponseInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Hyper\Server;
use Arcanum\Hyper\ServerAdapter;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\Request;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Phrase;
use Arcanum\Hyper\RequestMethod;
use Arcanum\Hyper\Version;
use Arcanum\Flow\River\CachingStream;
use Arcanum\Flow\River\EmptyStream;
use Arcanum\Flow\River\Stream;
use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\StreamResource;
use Arcanum\Flow\River\TemporaryStream;
use Arcanum\Gather\IgnoreCaseRegistry;
use Arcanum\Gather\Registry;
use Arcanum\Hyper\URI\URI;
use Arcanum\Hyper\URI\Spec;
use Arcanum\Hyper\URI\Authority;
use Arcanum\Hyper\URI\Fragment;
use Arcanum\Hyper\URI\Host;
use Arcanum\Hyper\URI\Path;
use Arcanum\Hyper\URI\Port;
use Arcanum\Hyper\URI\Query;
use Arcanum\Hyper\URI\Scheme;
use Arcanum\Hyper\URI\UserInfo;

#[CoversClass(Server::class)]
#[UsesClass(Response::class)]
#[UsesClass(Request::class)]
#[UsesClass(Message::class)]
#[UsesClass(Headers::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Phrase::class)]
#[UsesClass(RequestMethod::class)]
#[UsesClass(Version::class)]
#[UsesClass(CachingStream::class)]
#[UsesClass(EmptyStream::class)]
#[UsesClass(Stream::class)]
#[UsesClass(LazyResource::class)]
#[UsesClass(StreamResource::class)]
#[UsesClass(TemporaryStream::class)]
#[UsesClass(IgnoreCaseRegistry::class)]
#[UsesClass(Registry::class)]
#[UsesClass(URI::class)]
#[UsesClass(Spec::class)]
#[UsesClass(Authority::class)]
#[UsesClass(Fragment::class)]
#[UsesClass(Host::class)]
#[UsesClass(Path::class)]
#[UsesClass(Port::class)]
#[UsesClass(Query::class)]
#[UsesClass(Scheme::class)]
#[UsesClass(UserInfo::class)]
#[UsesClass(\Arcanum\Hyper\ServerRequest::class)]
#[UsesClass(\Arcanum\Hyper\Files\UploadedFiles::class)]
final class ServerTest extends TestCase
{
    private function createAdapter(): MockObject&ServerAdapter
    {
        return $this->createMock(ServerAdapter::class);
    }

    /**
     * @param array<string, string[]> $headers
     */
    private function createResponse(
        StatusCode $statusCode = StatusCode::OK,
        array $headers = [],
        string $body = '',
        string $protocolVersion = '1.1',
    ): Response {
        $headerObj = new Headers($headers);
        $resource = LazyResource::for('php://memory', 'w+');
        if ($body !== '') {
            $resource->fwrite($body);
            $resource->fseek(0);
        }
        $stream = new Stream($resource);
        $message = new Message($headerObj, $stream, Version::from($protocolVersion));
        return new Response($message, $statusCode);
    }

    private function createRequest(
        RequestMethod $method = RequestMethod::GET,
        string $uri = 'https://example.com/',
        string $protocolVersion = '1.1',
    ): Request {
        $headers = new Headers([]);
        $stream = new Stream(LazyResource::for('php://memory', 'r+'));
        $message = new Message($headers, $stream, Version::from($protocolVersion));
        return new Request($message, $method, new URI($uri));
    }

    // -----------------------------------------------------------
    // send()
    // -----------------------------------------------------------

    public function testSendWithFastCGIFinishRequest(): void
    {
        // Arrange
        $adapter = $this->createAdapter();
        $adapter->method('headersSent')->willReturn(false);
        $adapter->expects($this->once())->method('fastCGIFinishRequest')->willReturn(true);
        $adapter->expects($this->never())->method('litespeedFinishRequest');
        $adapter->expects($this->never())->method('flush');

        $server = new Server($adapter);
        $response = $this->createResponse(body: 'hello');

        // Act
        $server->send($response);
    }

    public function testSendWithLitespeedFinishRequest(): void
    {
        // Arrange
        $adapter = $this->createAdapter();
        $adapter->method('headersSent')->willReturn(false);
        $adapter->expects($this->once())->method('fastCGIFinishRequest')->willReturn(false);
        $adapter->expects($this->once())->method('litespeedFinishRequest')->willReturn(true);
        $adapter->expects($this->never())->method('flush');

        $server = new Server($adapter);
        $response = $this->createResponse(body: 'hello');

        // Act
        $server->send($response);
    }

    public function testSendFallsBackToFlushingOutputBuffers(): void
    {
        // Arrange
        $adapter = $this->createAdapter();
        $adapter->method('headersSent')->willReturn(false);
        $adapter->method('fastCGIFinishRequest')->willReturn(false);
        $adapter->method('litespeedFinishRequest')->willReturn(false);
        $adapter->expects($this->once())->method('flush');
        $adapter->method('obGetStatus')->willReturn([]);

        $server = new Server($adapter);
        $response = $this->createResponse(body: 'hello');

        // Act
        $server->send($response);
    }

    public function testSendClosesOutputBuffersWithDelFlag(): void
    {
        // Arrange
        $adapter = $this->createAdapter();
        $adapter->method('headersSent')->willReturn(false);
        $adapter->method('fastCGIFinishRequest')->willReturn(false);
        $adapter->method('litespeedFinishRequest')->willReturn(false);
        $adapter->method('obGetStatus')->willReturn([
            ['del' => 1, 'flags' => 0],
        ]);
        $adapter->expects($this->once())->method('obEndFlush');
        $adapter->expects($this->once())->method('flush');

        $server = new Server($adapter);
        $response = $this->createResponse(body: 'hello');

        // Act
        $server->send($response);
    }

    public function testSendClosesOutputBuffersWithFlags(): void
    {
        // Arrange
        $adapter = $this->createAdapter();
        $adapter->method('headersSent')->willReturn(false);
        $adapter->method('fastCGIFinishRequest')->willReturn(false);
        $adapter->method('litespeedFinishRequest')->willReturn(false);
        $adapter->method('obGetStatus')->willReturn([
            ['flags' => \PHP_OUTPUT_HANDLER_REMOVABLE | \PHP_OUTPUT_HANDLER_FLUSHABLE],
        ]);
        $adapter->expects($this->once())->method('obEndFlush');

        $server = new Server($adapter);
        $response = $this->createResponse(body: 'hello');

        // Act
        $server->send($response);
    }

    public function testSendDoesNotCloseNonRemovableBuffers(): void
    {
        // Arrange
        $adapter = $this->createAdapter();
        $adapter->method('headersSent')->willReturn(false);
        $adapter->method('fastCGIFinishRequest')->willReturn(false);
        $adapter->method('litespeedFinishRequest')->willReturn(false);
        $adapter->method('obGetStatus')->willReturn([
            ['del' => 0, 'flags' => \PHP_OUTPUT_HANDLER_FLUSHABLE], // not removable, no del
        ]);
        $adapter->expects($this->never())->method('obEndFlush');

        $server = new Server($adapter);
        $response = $this->createResponse(body: 'hello');

        // Act
        $server->send($response);
    }

    public function testSendHandlesSingleBufferStatusArray(): void
    {
        // Arrange — adapter returns a single buffer status (not nested)
        $adapter = $this->createAdapter();
        $adapter->method('headersSent')->willReturn(false);
        $adapter->method('fastCGIFinishRequest')->willReturn(false);
        $adapter->method('litespeedFinishRequest')->willReturn(false);
        $adapter->method('obGetStatus')->willReturn(['level' => 1, 'del' => 1]);
        $adapter->expects($this->once())->method('obEndFlush');

        $server = new Server($adapter);
        $response = $this->createResponse(body: 'hello');

        // Act
        $server->send($response);
    }

    // -----------------------------------------------------------
    // sendHeadersFor()
    // -----------------------------------------------------------

    public function testSendSkipsHeadersIfAlreadySent(): void
    {
        // Arrange
        $adapter = $this->createAdapter();
        $adapter->method('headersSent')->willReturn(true);
        $adapter->expects($this->never())->method('header');
        $adapter->method('fastCGIFinishRequest')->willReturn(true);

        $server = new Server($adapter);
        $response = $this->createResponse(
            headers: ['X-Foo' => ['bar']],
            body: 'hello',
        );

        // Act
        $server->send($response);
    }

    public function testSendHeadersSendsStatusLineAndHeaders(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $adapter->method('headersSent')->willReturn(false);
        $adapter->method('fastCGIFinishRequest')->willReturn(true);

        $sentHeaders = [];
        $adapter->method('header')->willReturnCallback(
            function (string $header) use (&$sentHeaders) {
                $sentHeaders[] = $header;
            }
        );

        $server = new Server($adapter);
        $response = $this->createResponse(
            headers: ['X-Custom' => ['value1']],
            body: 'test',
        );

        // Act
        $server->send($response);

        // Assert
        $this->assertContains('X-Custom: value1', $sentHeaders);
        $this->assertContains('HTTP/1.1 200 OK', $sentHeaders);
    }

    // -----------------------------------------------------------
    // sendSetCookieHeaders()
    // -----------------------------------------------------------

    public function testSendSetCookieHeadersSendsEachCookieSeparately(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $adapter->method('headersSent')->willReturn(false);
        $adapter->method('fastCGIFinishRequest')->willReturn(true);

        $sentHeaders = [];
        $adapter->method('header')->willReturnCallback(
            function (string $header, bool $replace) use (&$sentHeaders) {
                $sentHeaders[] = ['header' => $header, 'replace' => $replace];
            }
        );

        $server = new Server($adapter);
        $response = $this->createResponse(
            headers: [
                'Set-Cookie' => ['session=abc123; Path=/', 'theme=dark; Path=/'],
                'Content-Type' => ['text/html'],
            ],
            body: 'hello',
        );

        // Act
        $server->send($response);

        // Assert — Set-Cookie headers must be sent with replace=false
        $cookieHeaders = array_filter(
            $sentHeaders,
            fn($h) => str_starts_with($h['header'], 'Set-Cookie:')
        );
        $this->assertCount(2, $cookieHeaders);
        foreach ($cookieHeaders as $h) {
            $this->assertFalse($h['replace']);
        }
        $cookieValues = array_column(array_values($cookieHeaders), 'header');
        $this->assertContains('Set-Cookie: session=abc123; Path=/', $cookieValues);
        $this->assertContains('Set-Cookie: theme=dark; Path=/', $cookieValues);
    }

    public function testSendSetCookieHeadersDoNotAppearInNonCookieHeaders(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $adapter->method('headersSent')->willReturn(false);
        $adapter->method('fastCGIFinishRequest')->willReturn(true);

        $sentHeaders = [];
        $adapter->method('header')->willReturnCallback(
            function (string $header) use (&$sentHeaders) {
                $sentHeaders[] = $header;
            }
        );

        $server = new Server($adapter);
        $response = $this->createResponse(
            headers: [
                'Set-Cookie' => ['session=abc123'],
                'X-Custom' => ['value'],
            ],
            body: 'hello',
        );

        // Act
        $server->send($response);

        // Assert — X-Custom should be sent, but Set-Cookie should not appear
        // in the non-cookie header loop (only via sendSetCookieHeaders)
        $nonStatusHeaders = array_filter(
            $sentHeaders,
            fn($h) => !str_starts_with($h, 'HTTP/') && !str_starts_with($h, 'Set-Cookie:')
        );
        $this->assertContains('X-Custom: value', $nonStatusHeaders);
    }

    public function testSendDoesNotMutateResponseWhenStrippingSetCookieHeaders(): void
    {
        // Arrange — verifies the withoutHeader mutation bug is fixed
        $adapter = $this->createStub(ServerAdapter::class);
        $adapter->method('headersSent')->willReturn(false);
        $adapter->method('fastCGIFinishRequest')->willReturn(true);

        $server = new Server($adapter);
        $response = $this->createResponse(
            headers: [
                'Set-Cookie' => ['session=abc123'],
                'Content-Type' => ['text/html'],
            ],
            body: 'hello',
        );

        // Act
        $server->send($response);

        // Assert — original response still has Set-Cookie
        $this->assertTrue($response->hasHeader('Set-Cookie'));
        $this->assertSame(['session=abc123'], $response->getHeader('Set-Cookie'));
    }

    // -----------------------------------------------------------
    // sendBodyFor()
    // -----------------------------------------------------------

    public function testSendEchoesBody(): void
    {
        // Arrange
        $adapter = $this->createAdapter();
        $adapter->method('headersSent')->willReturn(false);
        $adapter->method('fastCGIFinishRequest')->willReturn(true);
        $adapter->expects($this->once())->method('echo')->with('hello world');

        $server = new Server($adapter);
        $response = $this->createResponse(body: 'hello world');

        // Act
        $server->send($response);
    }

    // -----------------------------------------------------------
    // composeResponse()
    // -----------------------------------------------------------

    public function testComposeResponseStripsBodyForHEADRequest(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest(method: RequestMethod::HEAD);
        $response = $this->createResponse(
            headers: ['Content-Length' => ['18']],
            body: 'should be stripped',
        );

        // Act
        $result = $server->composeResponse($request, $response);

        // Assert
        $this->assertSame('', (string) $result->getBody());
        $this->assertSame('18', $result->getHeaderLine('Content-Length'));
    }

    public function testComposeResponseStripsBodyAndHeadersForInformationalResponse(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest();
        $response = $this->createResponse(
            statusCode: StatusCode::Continue,
            headers: ['Content-Type' => ['text/html'], 'Content-Length' => ['5']],
            body: 'hello',
        );

        // Act
        $result = $server->composeResponse($request, $response);

        // Assert
        $this->assertSame('', (string) $result->getBody());
        $this->assertFalse($result->hasHeader('Content-Type'));
        $this->assertFalse($result->hasHeader('Content-Length'));
    }

    public function testComposeResponseStripsContentLengthWhenTransferEncodingPresent(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest();
        $response = $this->createResponse(
            headers: [
                'Transfer-Encoding' => ['chunked'],
                'Content-Length' => ['100'],
            ],
            body: 'test',
        );

        // Act
        $result = $server->composeResponse($request, $response);

        // Assert
        $this->assertTrue($result->hasHeader('Transfer-Encoding'));
        $this->assertFalse($result->hasHeader('Content-Length'));
    }

    public function testComposeResponseAddsCharsetToTextContentType(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest();
        $response = $this->createResponse(
            headers: ['Content-Type' => ['text/html']],
            body: '<h1>Hi</h1>',
        );

        // Act
        $result = $server->composeResponse($request, $response);

        // Assert
        $this->assertSame('text/html; charset=UTF-8', $result->getHeaderLine('Content-Type'));
    }

    public function testComposeResponseDoesNotAddCharsetIfAlreadyPresent(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest();
        $response = $this->createResponse(
            headers: ['Content-Type' => ['text/html; charset=ISO-8859-1']],
            body: '<h1>Hi</h1>',
        );

        // Act
        $result = $server->composeResponse($request, $response);

        // Assert
        $this->assertSame('text/html; charset=ISO-8859-1', $result->getHeaderLine('Content-Type'));
    }

    public function testComposeResponseDoesNotAddCharsetToNonTextContentType(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest();
        $response = $this->createResponse(
            headers: ['Content-Type' => ['application/json']],
            body: '{}',
        );

        // Act
        $result = $server->composeResponse($request, $response);

        // Assert
        $this->assertSame('application/json', $result->getHeaderLine('Content-Type'));
    }

    public function testComposeResponseDoesNotAddCharsetWhenNoContentType(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest();
        $response = $this->createResponse(body: 'no content type');

        // Act
        $result = $server->composeResponse($request, $response);

        // Assert
        $this->assertFalse($result->hasHeader('Content-Type'));
    }

    public function testComposeResponseAddsPragmaAndExpiresForHttp10NoCache(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest();
        $response = $this->createResponse(
            headers: ['Cache-Control' => ['no-cache']],
            protocolVersion: '1.0',
        );

        // Act
        $result = $server->composeResponse($request, $response);

        // Assert
        $this->assertSame('no-cache', $result->getHeaderLine('pragma'));
        $this->assertSame('Thu, 19 Nov 1981 08:52:00 GMT', $result->getHeaderLine('expires'));
    }

    public function testComposeResponseDoesNotAddPragmaForHttp10WithoutNoCache(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest();
        $response = $this->createResponse(
            headers: ['Cache-Control' => ['public']],
            protocolVersion: '1.0',
        );

        // Act
        $result = $server->composeResponse($request, $response);

        // Assert
        $this->assertFalse($result->hasHeader('pragma'));
    }

    public function testComposeResponseDoesNotAddPragmaForHttp11NoCache(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest();
        $response = $this->createResponse(
            headers: ['Cache-Control' => ['no-cache']],
            protocolVersion: '1.1',
        );

        // Act
        $result = $server->composeResponse($request, $response);

        // Assert
        $this->assertFalse($result->hasHeader('pragma'));
    }

    public function testComposeResponseDoesNotAddPragmaWhenNoCacheControlHeader(): void
    {
        // Arrange
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest();
        $response = $this->createResponse(protocolVersion: '1.0');

        // Act
        $result = $server->composeResponse($request, $response);

        // Assert
        $this->assertFalse($result->hasHeader('pragma'));
    }

    public function testComposeResponseSkipsCharsetForNonHasCharacterSetResponse(): void
    {
        // Arrange — use a plain PSR-7 mock that doesn't implement HasCharacterSet
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest();

        $body = $this->createStub(\Psr\Http\Message\StreamInterface::class);
        $body->method('getSize')->willReturn(5);

        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);
        $response->method('getProtocolVersion')->willReturn('1.1');
        $response->method('hasHeader')->willReturnCallback(fn(string $name) => $name === 'Content-Type');
        $response->method('getHeaderLine')->willReturnCallback(fn(string $name) => match ($name) {
            'Content-Type' => 'text/html',
            default => '',
        });
        $response->method('getBody')->willReturn($body);
        $response->method('getHeaders')->willReturn(['Content-Type' => ['text/html']]);
        $response->method('getHeader')->willReturn([]);
        $response->method('withoutHeader')->willReturn($response);
        $response->method('withHeader')->willReturn($response);
        $response->method('withBody')->willReturn($response);

        // Act
        $result = $server->composeResponse($request, $response);

        // Assert — charset is NOT added because $response doesn't implement HasCharacterSet
        $this->assertSame('text/html', $result->getHeaderLine('Content-Type'));
    }

    public function testComposeResponseRestoresDefaultMimetypeAfterInformational(): void
    {
        // Arrange — send an informational response first, then a normal one.
        // The first call disables default_mimetype, the second should restore it.
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest();

        $informational = $this->createResponse(statusCode: StatusCode::Continue);
        $normal = $this->createResponse(body: 'OK');

        $originalMimetype = \ini_get('default_mimetype');

        // Act
        $server->composeResponse($request, $informational);
        $server->composeResponse($request, $normal);

        // Assert
        $this->assertSame($originalMimetype, \ini_get('default_mimetype'));
    }

    public function testComposeResponseRestoreSkipsWhenNotPreviouslyDisabled(): void
    {
        // Arrange — just compose a normal response without prior informational.
        // restoreDefaultMimetypeINI should return early because defaultMimetypeINI is null.
        $adapter = $this->createStub(ServerAdapter::class);
        $server = new Server($adapter);
        $request = $this->createRequest();
        $response = $this->createResponse(body: 'OK');

        $originalMimetype = \ini_get('default_mimetype');

        // Act
        $server->composeResponse($request, $response);

        // Assert
        $this->assertSame($originalMimetype, \ini_get('default_mimetype'));
    }

    // -----------------------------------------------------------
    // request() — parsed body for non-POST methods
    // -----------------------------------------------------------

    public function testRequestPostUsesPostSuperglobal(): void
    {
        // Arrange
        $originalServer = $_SERVER;
        $originalPost = $_POST;
        $originalGet = $_GET;
        $originalCookie = $_COOKIE;

        try {
            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
            $_SERVER['HTTP_HOST'] = 'localhost';
            $_SERVER['REQUEST_URI'] = '/test';
            $_POST = ['field' => 'value'];
            $_GET = [];
            $_COOKIE = [];

            $adapter = $this->createAdapter();
            $adapter->method('getallheaders')->willReturn([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]);

            $server = new Server($adapter);

            // Act
            $request = $server->request();

            // Assert — POST uses $_POST directly
            $this->assertSame(['field' => 'value'], $request->getParsedBody());
        } finally {
            $_SERVER = $originalServer;
            $_POST = $originalPost;
            $_GET = $originalGet;
            $_COOKIE = $originalCookie;
        }
    }

    public function testRequestGetReturnsNullParsedBody(): void
    {
        // Arrange
        $originalServer = $_SERVER;
        $originalPost = $_POST;
        $originalGet = $_GET;
        $originalCookie = $_COOKIE;

        try {
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
            $_SERVER['HTTP_HOST'] = 'localhost';
            $_SERVER['REQUEST_URI'] = '/test';
            $_POST = [];
            $_GET = [];
            $_COOKIE = [];

            $adapter = $this->createAdapter();
            $adapter->method('getallheaders')->willReturn([]);

            $server = new Server($adapter);

            // Act
            $request = $server->request();

            // Assert — GET always returns null parsed body
            $this->assertNull($request->getParsedBody());
        } finally {
            $_SERVER = $originalServer;
            $_POST = $originalPost;
            $_GET = $originalGet;
            $_COOKIE = $originalCookie;
        }
    }

    public function testRequestPutWithFormEncodedContentTypeParseBody(): void
    {
        // Arrange — php://input is empty in CLI, so parse_str produces []
        $originalServer = $_SERVER;
        $originalPost = $_POST;
        $originalGet = $_GET;
        $originalCookie = $_COOKIE;

        try {
            $_SERVER['REQUEST_METHOD'] = 'PUT';
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
            $_SERVER['HTTP_HOST'] = 'localhost';
            $_SERVER['REQUEST_URI'] = '/test';
            $_POST = [];
            $_GET = [];
            $_COOKIE = [];

            $adapter = $this->createAdapter();
            $adapter->method('getallheaders')->willReturn([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]);

            $server = new Server($adapter);

            // Act
            $request = $server->request();

            // Assert — parsed body is an array (not null), proving the parsing code path ran
            $this->assertIsArray($request->getParsedBody());
        } finally {
            $_SERVER = $originalServer;
            $_POST = $originalPost;
            $_GET = $originalGet;
            $_COOKIE = $originalCookie;
        }
    }

    public function testRequestPatchWithFormEncodedContentTypeParseBody(): void
    {
        // Arrange
        $originalServer = $_SERVER;
        $originalPost = $_POST;
        $originalGet = $_GET;
        $originalCookie = $_COOKIE;

        try {
            $_SERVER['REQUEST_METHOD'] = 'PATCH';
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
            $_SERVER['HTTP_HOST'] = 'localhost';
            $_SERVER['REQUEST_URI'] = '/test';
            $_POST = [];
            $_GET = [];
            $_COOKIE = [];

            $adapter = $this->createAdapter();
            $adapter->method('getallheaders')->willReturn([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]);

            $server = new Server($adapter);

            // Act
            $request = $server->request();

            // Assert
            $this->assertIsArray($request->getParsedBody());
        } finally {
            $_SERVER = $originalServer;
            $_POST = $originalPost;
            $_GET = $originalGet;
            $_COOKIE = $originalCookie;
        }
    }

    public function testRequestDeleteWithFormEncodedContentTypeParseBody(): void
    {
        // Arrange
        $originalServer = $_SERVER;
        $originalPost = $_POST;
        $originalGet = $_GET;
        $originalCookie = $_COOKIE;

        try {
            $_SERVER['REQUEST_METHOD'] = 'DELETE';
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
            $_SERVER['HTTP_HOST'] = 'localhost';
            $_SERVER['REQUEST_URI'] = '/test';
            $_POST = [];
            $_GET = [];
            $_COOKIE = [];

            $adapter = $this->createAdapter();
            $adapter->method('getallheaders')->willReturn([
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]);

            $server = new Server($adapter);

            // Act
            $request = $server->request();

            // Assert
            $this->assertIsArray($request->getParsedBody());
        } finally {
            $_SERVER = $originalServer;
            $_POST = $originalPost;
            $_GET = $originalGet;
            $_COOKIE = $originalCookie;
        }
    }

    public function testRequestPutWithJsonContentTypeReturnsNullParsedBody(): void
    {
        // Arrange — JSON bodies should not be auto-parsed; the stream is available for manual reading
        $originalServer = $_SERVER;
        $originalPost = $_POST;
        $originalGet = $_GET;
        $originalCookie = $_COOKIE;

        try {
            $_SERVER['REQUEST_METHOD'] = 'PUT';
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
            $_SERVER['HTTP_HOST'] = 'localhost';
            $_SERVER['REQUEST_URI'] = '/test';
            $_POST = [];
            $_GET = [];
            $_COOKIE = [];

            $adapter = $this->createAdapter();
            $adapter->method('getallheaders')->willReturn([
                'Content-Type' => 'application/json',
            ]);

            $server = new Server($adapter);

            // Act
            $request = $server->request();

            // Assert — null, not parsed
            $this->assertNull($request->getParsedBody());
        } finally {
            $_SERVER = $originalServer;
            $_POST = $originalPost;
            $_GET = $originalGet;
            $_COOKIE = $originalCookie;
        }
    }

    public function testRequestPutWithNoContentTypeReturnsNullParsedBody(): void
    {
        // Arrange
        $originalServer = $_SERVER;
        $originalPost = $_POST;
        $originalGet = $_GET;
        $originalCookie = $_COOKIE;

        try {
            $_SERVER['REQUEST_METHOD'] = 'PUT';
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
            $_SERVER['HTTP_HOST'] = 'localhost';
            $_SERVER['REQUEST_URI'] = '/test';
            $_POST = [];
            $_GET = [];
            $_COOKIE = [];

            $adapter = $this->createAdapter();
            $adapter->method('getallheaders')->willReturn([]);

            $server = new Server($adapter);

            // Act
            $request = $server->request();

            // Assert
            $this->assertNull($request->getParsedBody());
        } finally {
            $_SERVER = $originalServer;
            $_POST = $originalPost;
            $_GET = $originalGet;
            $_COOKIE = $originalCookie;
        }
    }
}
