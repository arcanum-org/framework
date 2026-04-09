<?php

declare(strict_types=1);

namespace Arcanum\Testing;

use Arcanum\Cabinet\Application;
use Arcanum\Flow\River\EmptyStream;
use Arcanum\Flow\River\TemporaryStream;
use Arcanum\Gather\Registry;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Request;
use Arcanum\Hyper\RequestMethod;
use Arcanum\Hyper\ServerRequest;
use Arcanum\Hyper\URI\URI;
use Arcanum\Hyper\Version;
use Arcanum\Testing\Internal\TestHyperKernel;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Fluent HTTP surface for tests.
 *
 * Translates `get('/path')` / `post('/path', $body)` style calls into real
 * PSR-7 ServerRequest objects and dispatches them through a wrapped
 * HyperKernel, returning the resulting `ResponseInterface`. The kernel goes
 * through the same exception-handling, middleware, and lifecycle paths
 * production uses, so tests observe what real handlers observe.
 *
 * Default headers set via `withHeader()` persist across requests on the
 * same surface — same ergonomics as configuring an HTTP client.
 *
 * `setCoreHandler()` installs a PSR-15 handler the kernel will dispatch to.
 * With no handler installed, every request renders the kernel's standard
 * 404 response — useful for verifying the round-trip works end-to-end.
 */
final class HttpTestSurface
{
    /** @var array<string, string> */
    private array $defaultHeaders = [];

    private bool $bootstrapped = false;

    public function __construct(
        private readonly TestHyperKernel $kernel,
        private readonly Application $container,
    ) {
    }

    public function setCoreHandler(RequestHandlerInterface|null $handler): self
    {
        $this->kernel->setCoreHandler($handler);

        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->defaultHeaders[$name] = $value;

        return $this;
    }

    public function get(string $path): ResponseInterface
    {
        return $this->dispatch(RequestMethod::GET, $path, null);
    }

    public function post(string $path, string|null $body = null): ResponseInterface
    {
        return $this->dispatch(RequestMethod::POST, $path, $body);
    }

    public function put(string $path, string|null $body = null): ResponseInterface
    {
        return $this->dispatch(RequestMethod::PUT, $path, $body);
    }

    public function patch(string $path, string|null $body = null): ResponseInterface
    {
        return $this->dispatch(RequestMethod::PATCH, $path, $body);
    }

    public function delete(string $path, string|null $body = null): ResponseInterface
    {
        return $this->dispatch(RequestMethod::DELETE, $path, $body);
    }

    private function dispatch(
        RequestMethod $method,
        string $path,
        string|null $body,
    ): ResponseInterface {
        if (!$this->bootstrapped) {
            $this->kernel->bootstrap($this->container);
            $this->bootstrapped = true;
        }

        return $this->kernel->handle($this->buildRequest($method, $path, $body));
    }

    private function buildRequest(
        RequestMethod $method,
        string $path,
        string|null $body,
    ): ServerRequestInterface {
        $uri = new URI('http://localhost' . $path);
        $headers = new Headers($this->defaultHeaders);
        $stream = $body === null || $body === '' ? new EmptyStream() : $this->streamFor($body);
        $message = new Message($headers, $stream, Version::from('1.1'));
        $request = new Request($message, $method, $uri);
        $serverRequest = new ServerRequest($request, new Registry([]));

        $query = $uri->getQuery();
        if ($query !== '') {
            parse_str($query, $parsed);
            /** @var array<string, mixed> $params */
            $params = [];
            foreach ($parsed as $key => $value) {
                $params[(string) $key] = $value;
            }
            $serverRequest = $serverRequest->withQueryParams($params);
        }

        return $serverRequest;
    }

    private function streamFor(string $body): StreamInterface
    {
        $stream = TemporaryStream::getNew();
        $stream->write($body);
        $stream->rewind();

        return $stream;
    }
}
