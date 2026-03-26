<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class Response implements ResponseInterface, HasCharacterSet
{
    /**
     * Create a new response.
     */
    public function __construct(
        protected MessageInterface $message,
        protected StatusCode $statusCode = StatusCode::OK,
        protected Phrase|null $reasonPhrase = null,
        protected string $charset = self::UTF8,
    ) {
    }

    /**
     * Get the character set.
     */
    public function getCharacterSet(): string
    {
        return $this->charset;
    }

    /**
     * Get the status code.
     */
    public function getStatusCode(): int
    {
        return $this->statusCode->value;
    }

    /**
     * Get the reason phrase.
     */
    public function getReasonPhrase(): string
    {
        return $this->reasonPhrase !== null
            ? $this->reasonPhrase->value
            : $this->statusCode->reason()->value;
    }

    /**
     * Return an instance with the specified status code and, optionally, reason phrase.
     */
    public function withStatus(int $code, string $reasonPhrase = ''): ResponseInterface
    {
        $response = clone $this;
        $response->statusCode = StatusCode::from($code);
        $response->reasonPhrase = Phrase::tryFrom($reasonPhrase);
        return $response;
    }

    /**
     * Message methods
     */

    /**
     * Get the protocol version.
     */
    public function getProtocolVersion(): string
    {
        return $this->message->getProtocolVersion();
    }

    /**
     * Return an instance with the specified protocol version.
     */
    public function withProtocolVersion(string $version): MessageInterface
    {
        $response = clone $this;
        $response->message = $this->message->withProtocolVersion($version);
        return $response;
    }

    /**
     * Retrieve all message header values.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     */
    public function getHeaders(): array
    {
        return $this->message->getHeaders();
    }

    /**
     * Get a header by the given case-insensitive name.
     */
    public function getHeader(string $name): array
    {
        return $this->message->getHeader($name);
    }

    /**
     * Get a comma-separated string of the values for a single header.
     */
    public function getHeaderLine(string $name): string
    {
        return $this->message->getHeaderLine($name);
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     */
    public function hasHeader(string $name): bool
    {
        return $this->message->hasHeader($name);
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     *
     * @param string|string[] $value header value(s)
     */
    public function withHeader(string $name, $value): MessageInterface
    {
        $response = clone $this;
        $response->message = $this->message->withHeader($name, $value);
        return $response;
    }

    /**
     * Return an instance with the specified header appended with the given value.
     *
     * @param string|string[] $value header value(s)
     */
    public function withAddedHeader(string $name, $value): MessageInterface
    {
        $response = clone $this;
        $response->message = $this->message->withAddedHeader($name, $value);
        return $response;
    }

    /**
     * Return an instance without the specified header.
     */
    public function withoutHeader(string $name): MessageInterface
    {
        $response = clone $this;
        $response->message = $this->message->withoutHeader($name);
        return $response;
    }

    /**
     * Get the body of the message.
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
        $response = clone $this;
        $response->message = $this->message->withBody($body);
        return $response;
    }
}
