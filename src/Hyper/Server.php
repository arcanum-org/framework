<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Flow\River\CachingStream;
use Arcanum\Flow\River\EmptyStream;
use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Gather\Registry;
use Arcanum\Hyper\Files\UploadedFiles;
use Arcanum\Hyper\URI\Spec;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class Server
{
    /**
     * @see https://github.com/php/php-src/commit/996216b4991f74c749d7c47df817daa7ec2f1740
     */
    private const EXPIRES = 'Thu, 19 Nov 1981 08:52:00 GMT';

    /**
     * When normalizing a response before sending it to the client,
     * we sometimes need to override the php ini setting for default_mimetype.
     *
     * This is the original value of that setting, before we override it.
     */
    private string|false|null $defaultMimetypeINI = null;

    /**
     * Create a new Hyper Server.
     */
    public function __construct(private ServerAdapter $adapter)
    {
    }

    /**
     * Get a ServerRequestInterface from the current request.
     */
    public function request(): ServerRequestInterface
    {
        $serverParams = new Registry($_SERVER);
        $method = RequestMethod::from($serverParams->asString('REQUEST_METHOD', 'GET'));
        $headers = new Headers($this->adapter->getallheaders());
        $uri = Spec::fromServerParams($serverParams);
        $body = CachingStream::fromStream(new Stream(LazyResource::for('php://input', 'r+')));
        $protocolVersion = Version::from(
            str_replace('HTTP/', '', $serverParams->asString('SERVER_PROTOCOL', '1.1'))
        );

        $message = new Message($headers, $body, $protocolVersion);
        $request = new Request($message, $method, $uri);
        $serverRequest = new ServerRequest($request, $serverParams);

        $cookies = [];
        foreach ($_COOKIE as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $cookies[$name] = $value;
            }
        }

        return $serverRequest
            ->withCookieParams($cookies)
            ->withQueryParams($_GET)
            ->withParsedBody($_POST)
            ->withUploadedFiles(UploadedFiles::fromSuperGlobal()->toArray());
    }

    /**
     * Send a ResponseInterface to the client.
     */
    public function send(ResponseInterface $response): void
    {
        $this->sendHeadersFor($response);
        $this->sendBodyFor($response);

        if ($this->adapter->fastCGIFinishRequest()) {
            return;
        }

        if ($this->adapter->litespeedFinishRequest()) {
            return;
        }

        $this->closeOutputBuffers(targetLevel: 0, flush: true);
        $this->adapter->flush();
    }


    /**
     * Can be called before sending the response to the client to prepare the response
     * before it is sent.
     */
    public function composeResponse(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if ($request->getMethod() === RequestMethod::HEAD->value) {
            $contentLength = $response->getHeaderLine('Content-Length');
            $response = $response
                ->withBody(new EmptyStream())
                ->withHeader('Content-Length', empty($contentLength) ? '0' : $contentLength);
        }

        if (StatusCode::from($response->getStatusCode())->isInformational()) {
            // 1xx responses MUST NOT include a body
            if ($response->getBody()->getSize() > 0) {
                $response = $response->withBody(new EmptyStream());
            }
            $response = $response
                ->withoutHeader('Content-Type')
                ->withoutHeader('Content-Length');

            // prevent PHP from sending the default Content-Type header.
            $this->disableDefaultMimetypeINI();
        } else {
            // restore the default_mimetype ini setting if it was previously changed.
            $this->restoreDefaultMimetypeINI();
            $response = $this->withContentTypeCharset($response);

            if ($response->hasHeader('Transfer-Encoding')) {
                $response = $response->withoutHeader('Content-Length');
            }
        }

        // add pragma and expires headers for HTTP/1.0 clients when the Cache-Control header
        // is set to no-cache
        if ($response->getProtocolVersion() !== '1.0' || !$response->hasHeader('Cache-Control')) {
            return $response;
        }

        $cacheControl = $response->getHeaderLine('Cache-Control');
        if (\str_contains($cacheControl, 'no-cache')) {
            return $response
                ->withHeader('pragma', 'no-cache')
                ->withHeader('expires', self::EXPIRES);
        }

        return $response;
    }

    /**
     * Change the default_mimetype ini setting to an empty value.
     */
    protected function disableDefaultMimetypeINI(): void
    {
        $this->defaultMimetypeINI = \ini_get('default_mimetype');
        \ini_set('default_mimetype', '');
    }

    /**
     * Restore the default_mimetype ini setting to its original value if it was
     * previously changed.
     */
    protected function restoreDefaultMimetypeINI(): void
    {
        if ($this->defaultMimetypeINI === null) {
            return;
        }
        \ini_set('default_mimetype', $this->defaultMimetypeINI);
        $this->defaultMimetypeINI = null;
    }

    /**
     * If the response has a Content-Type header that starts with "text/", we
     * need to make sure it has a charset.
     */
    protected function withContentTypeCharset(ResponseInterface $response): ResponseInterface
    {
        if (!$response->hasHeader('Content-Type')) {
            return $response;
        }

        if (!$response instanceof HasCharacterSet) {
            return $response;
        }

        $contentType = $response->getHeaderLine('Content-Type');
        if (\str_starts_with($contentType, 'text/') && !\str_contains($contentType, 'charset=')) {
            return $response->withHeader('Content-Type', $contentType . '; charset=' . $response->getCharacterSet());
        }
        return $response;
    }

    /**
     * Send headers for a ResponseInterface to the client.
     */
    private function sendHeadersFor(ResponseInterface $response): void
    {
        if ($this->adapter->headersSent()) {
            return;
        }

        $statusCode = $response->getStatusCode();

        $this->sendNonCookieHeaders($response);
        $this->sendSetCookieHeaders($response);

        // Send status line
        $protocolVersion = $response->getProtocolVersion();
        $reasonPhrase = $response->getReasonPhrase();
        $this->adapter->header("HTTP/$protocolVersion $statusCode $reasonPhrase", true, $statusCode);
    }

    /**
     * Send all headers for a ResponseInterface to the client except for Set-Cookie.
     */
    private function sendNonCookieHeaders(ResponseInterface $response): void
    {
        // Send all headers except for Set-Cookie
        $statusCode = $response->getStatusCode();
        foreach ($response->withoutHeader('Set-Cookie')->getHeaders() as $name => $values) {
            $this->sendHeaders((string) $name, $values, $statusCode);
        }
    }

    /**
     * Send all Set-Cookie headers for a ResponseInterface to the client.
     */
    private function sendSetCookieHeaders(ResponseInterface $response): void
    {
        $statusCode = $response->getStatusCode();
        foreach ($response->getHeader('Set-Cookie') as $cookie) {
            $this->adapter->header("Set-Cookie: $cookie", false, $statusCode);
        }
    }

    /**
     * Loop over all $values and send a header for each.
     *
     * @param string[] $values
     */
    private function sendHeaders(string $name, array $values, int $statusCode = 0): void
    {
        foreach ($values as $value) {
            $this->adapter->header("$name: $value", false, $statusCode);
        }
    }

    /**
     * Send the body for a ResponseInterface to the client.
     */
    private function sendBodyFor(ResponseInterface $response): void
    {
        $body = $response->getBody();

        $body->isSeekable() && $body->rewind();

        $this->adapter->echo($body->getContents());
    }

    /**
     * Close all output buffers.
     */
    private function closeOutputBuffers(int $targetLevel, bool $flush): void
    {
        $status = $this->adapter->obGetStatus(fullStatus: true);

        if (empty($status)) {
            return;
        }

        if (!isset($status[0]) || !is_array($status[0])) {
            // The adapter returned only a single buffer even though
            // we passed the full_status=true flag. This should only happen
            // with a poor adapter implementation. Nevertheless, we need to
            // handle it.
            $status = [$status];
        }
        /** @var array{ del?: int, flags?: int }[] $status */
        $level = \count($status);
        $method = $flush ? 'obEndFlush' : 'obEndClean';
        while ($level-- > $targetLevel && self::canClose($status[$level], $flush)) {
            $this->adapter->$method();
        }
    }

    /**
     * Determine if the output buffer can be closed.
     *
     * @param array{ del?: int, flags?: int } $level
     */
    private static function canClose(array $level, bool $flush): bool
    {
        return !empty($level['del']) || (isset($level['flags']) && self::compareFlags($level['flags'], $flush));
    }

    /**
     * Compare flags to determine if the buffer is removable and either flushable
     * or cleanable.
     */
    private static function compareFlags(int $flags, bool $flush): bool
    {
        $flushableOrCleanable = $flush ? \PHP_OUTPUT_HANDLER_FLUSHABLE : \PHP_OUTPUT_HANDLER_CLEANABLE;
        return ( $flags & \PHP_OUTPUT_HANDLER_REMOVABLE ) && ( $flags & $flushableOrCleanable );
    }
}
