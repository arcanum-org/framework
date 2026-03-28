<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

class Request implements RequestInterface, \Stringable
{
    /**
     * If this is set, it will override the request target of the URI.
     */
    protected string|null $requestTarget = null;

    public function __construct(
        protected MessageInterface $message,
        protected RequestMethod $method,
        protected UriInterface $uri,
    ) {
        if ($this->message->hasHeader('Host')) {
            $this->setHostHeaderFromURI();
        }
    }

    /**
     * Set the Host header from the URI, if possible.
     */
    protected function setHostHeaderFromURI(): void
    {
        $host = $this->uri->getHost();

        if ($host === '') {
            return;
        }

        $port = $this->uri->getPort();

        if ($port !== null) {
            $host .= ":$port";
        }

        $this->message = $this->message->withHeader('Host', $host);
    }

    /**
     * Retrieves the message's request target.
     */
    public function getRequestTarget(): string
    {
        if ($this->requestTarget !== null) {
            return $this->requestTarget;
        }

        $target = $this->uri->getPath();
        if ($target === '') {
            $target = '/';
        }

        $query = $this->uri->getQuery();
        if ($query !== '') {
            $target .= "?$query";
        }

        $this->requestTarget = $target;

        return $target;
    }

    /**
     * Return an instance with the specific request-target.
     */
    public function withRequestTarget(string $requestTarget): RequestInterface
    {
        if (!self::isValidRequestTarget($requestTarget)) {
            throw new \InvalidArgumentException(
                "Invalid request target: $requestTarget. "
                . 'Must be origin-form, absolute-form, authority-form, or asterisk-form per RFC 7230 §5.3.'
            );
        }
        $request = clone $this;
        $request->requestTarget = $requestTarget;
        return $request;
    }

    /**
     * Validate request target per RFC 7230 §5.3.
     *
     * Accepts:
     *   origin-form:    absolute-path [ "?" query ]  e.g. /path?query
     *   absolute-form:  absolute-URI                 e.g. http://example.com/path
     *   authority-form: host ":" port                e.g. example.com:443
     *   asterisk-form:  "*"
     */
    private static function isValidRequestTarget(string $target): bool
    {
        if ($target === '') {
            return false;
        }

        if (preg_match('/\s/', $target)) {
            return false;
        }

        // asterisk-form
        if ($target === '*') {
            return true;
        }

        // origin-form: starts with /
        if ($target[0] === '/') {
            return true;
        }

        // absolute-form: starts with scheme
        if (preg_match('#^https?://#i', $target)) {
            return true;
        }

        // authority-form: host:port
        if (preg_match('/^[^\s\/:]+:\d+$/', $target)) {
            return true;
        }

        return false;
    }


    /**
     * Retrieves the HTTP method of the request.
     */
    public function getMethod(): string
    {
        return $this->method->value;
    }

    /**
     * Return an instance with the provided HTTP method.
     */
    public function withMethod(string $method): RequestInterface
    {
        $request = clone $this;
        $request->method = RequestMethod::from($method);
        return $request;
    }

    /**
     * Retrieves the URI instance.
     */
    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    /**
     * Returns an instance with the provided URI.
     */
    public function withUri(UriInterface $uri, bool $preserveHost = false): RequestInterface
    {
        if ($uri === $this->uri) {
            return $this;
        }

        $request = clone $this;
        $request->uri = $uri;
        $request->requestTarget = null;

        if (!$preserveHost || empty($this->message->getHeader('Host'))) {
            $request->setHostHeaderFromURI();
        }

        return $request;
    }

    /**
     * MessageInterface methods
     */

    /**
     * Retrieves the HTTP protocol version as a string.
     */
    public function getProtocolVersion(): string
    {
        return $this->message->getProtocolVersion();
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     */
    public function withProtocolVersion(string $version): MessageInterface
    {
        $request = clone $this;
        $request->message = $request->message->withProtocolVersion($version);
        return $request;
    }

    /**
     * Retrieves all message header values.
     *
     * @return array<string, string[]>
     */
    public function getHeaders(): array
    {
        return $this->message->getHeaders();
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     */
    public function hasHeader(string $name): bool
    {
        return $this->message->hasHeader($name);
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * @return string[]
     */
    public function getHeader(string $name): array
    {
        return $this->message->getHeader($name);
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     */
    public function getHeaderLine(string $name): string
    {
        return $this->message->getHeaderLine($name);
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * @param string|string[] $value
     */
    public function withHeader(string $name, $value): MessageInterface
    {
        $request = clone $this;
        $request->message = $request->message->withHeader($name, $value);
        return $request;
    }

    /**
     * Return an instance with the specified header appended with the given value.
     *
     * @param string|string[] $value
     */
    public function withAddedHeader(string $name, $value): MessageInterface
    {
        $request = clone $this;
        $request->message = $request->message->withAddedHeader($name, $value);
        return $request;
    }

    /**
     * Return an instance without the specified header.
     */
    public function withoutHeader(string $name): MessageInterface
    {
        $request = clone $this;
        $request->message = $request->message->withoutHeader($name);
        return $request;
    }

    /**
     * Retrieves the body of the message.
     */
    public function getBody(): StreamInterface
    {
        return $this->message->getBody();
    }

    /**
     * Return an instance with the specified message body.
     */
    public function withBody(StreamInterface $body): MessageInterface
    {
        $request = clone $this;
        $request->message = $request->message->withBody($body);
        return $request;
    }

    /**
     * Request as string.
     */
    public function __toString(): string
    {
        $method = $this->getMethod();
        $target = $this->getRequestTarget();
        $protocol = $this->getProtocolVersion();

        $message = trim("$method $target") . " HTTP/$protocol";

        if ($this->hasHeader('Host')) {
            $message .= "\r\nHost: " . $this->getHeaderLine('Host');
        }
        if ($this->hasHeader('Set-Cookie')) {
            $message .= $this->cookieHeaderAsString($this->getHeader('Set-Cookie'));
        }
        foreach (array_keys($this->withoutHeader('Host')->withoutHeader('Set-Cookie')->getHeaders()) as $name) {
            $message .= "\r\n$name: " . $this->getHeaderLine($name);
        }
        return "$message\r\n\r\n" . $this->getBody();
    }

    /**
     * @param string[] $cookies
     */
    protected function cookieHeaderAsString(array $cookies): string
    {
        $cookieHeader = '';
        foreach ($cookies as $cookie) {
            $cookieHeader .= "\r\nSet-Cookie: $cookie";
        }
        return $cookieHeader;
    }
}
