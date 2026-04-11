<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture;

use Arcanum\Ignition\HyperKernel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Test kernel that captures the prepared request for assertion.
 */
class CapturingKernel extends HyperKernel
{
    public ServerRequestInterface|null $capturedRequest = null;

    protected function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->capturedRequest = $request;

        return new class () implements ResponseInterface {
            public function getStatusCode(): int
            {
                return 200;
            }

            public function withStatus(int $code, string $reasonPhrase = ''): static
            {
                return $this;
            }

            public function getReasonPhrase(): string
            {
                return '';
            }

            public function getProtocolVersion(): string
            {
                return '1.1';
            }

            public function withProtocolVersion(string $version): static
            {
                return $this;
            }

            public function getHeaders(): array
            {
                return [];
            }

            public function hasHeader(string $name): bool
            {
                return false;
            }

            public function getHeader(string $name): array
            {
                return [];
            }

            public function getHeaderLine(string $name): string
            {
                return '';
            }

            public function withHeader(string $name, $value): static
            {
                return $this;
            }

            public function withAddedHeader(string $name, $value): static
            {
                return $this;
            }

            public function withoutHeader(string $name): static
            {
                return $this;
            }

            public function getBody(): \Psr\Http\Message\StreamInterface
            {
                return new \Arcanum\Flow\River\EmptyStream();
            }

            public function withBody(\Psr\Http\Message\StreamInterface $body): static
            {
                return $this;
            }
        };
    }
}
