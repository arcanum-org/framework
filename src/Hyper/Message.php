<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

class Message implements MessageInterface
{
    public function __construct(
        protected Headers $headers,
        protected StreamInterface $body,
        protected Version $protocolVersion,
    ) {
    }

    public function __clone(): void
    {
        $this->headers = clone $this->headers;
    }

    /**
     * Retrieves the HTTP protocol version as a string.
     */
    public function getProtocolVersion(): string
    {
        return $this->protocolVersion->value;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     */
    public function withProtocolVersion(string $version): MessageInterface
    {
        $request = clone $this;
        $request->protocolVersion = Version::from($version);
        return $request;
    }

    /**
     * Retrieves all message header values.
     *
     * @return array<string, string[]>
     */
    public function getHeaders(): array
    {
        /** @var array<string, string[]> */
        return $this->headers->toArray();
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     */
    public function hasHeader(string $name): bool
    {
        return $this->headers->has($name);
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * @return string[]
     */
    public function getHeader(string $name): array
    {
        /** @var string[] */
        return $this->headers->get($name) ?? [];
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     */
    public function getHeaderLine(string $name): string
    {
        return implode(',', $this->getHeader($name));
    }

    /**
     * Return an instance with the provided value replacing the specified header.
     */
    public function withHeader(string $name, $value): MessageInterface
    {
        $request = clone $this;
        $request->headers[$name] = (array)$value;
        return $request;
    }

    /**
     * Return an instance with the specified header appended with the given value.
     */
    public function withAddedHeader(string $name, $value): MessageInterface
    {
        $request = clone $this;

        /** @var string[]|null */
        $headers = $request->headers->get($name);
        if ($headers === null) {
            $headers = [];
        }
        if (is_array($value)) {
            $headers = array_merge($headers, $value);
        } else {
            $headers[] = $value;
        }
        $request->headers->set($name, $headers);

        return $request;
    }

    /**
     * Return an instance without the specified header.
     */
    public function withoutHeader(string $name): MessageInterface
    {
        $request = clone $this;
        unset($request->headers[$name]);
        return $request;
    }

    /**
     * Gets the body of the message.
     */
    public function getBody(): StreamInterface
    {
        return $this->body;
    }

    /**
     * Return an instance with the specified message body.
     */
    public function withBody(StreamInterface $body): MessageInterface
    {
        if ($body === $this->body) {
            return $this;
        }

        $request = clone $this;
        $request->body = $body;
        return $request;
    }
}
