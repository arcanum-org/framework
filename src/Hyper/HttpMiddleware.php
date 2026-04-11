<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Flow\Pipeline\Pipeline;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Chains PSR-15 middleware around a core request handler.
 *
 * Middleware is executed in the order it was added — the first middleware
 * added is the outermost layer (runs first on the way in, last on the way out).
 *
 * Class-string middleware is resolved from the container at dispatch time,
 * so middleware can have constructor dependencies injected.
 *
 * Internally, the onion is built using Flow's Pipeline: each middleware
 * becomes a Pipeline Stage that takes a handler in and returns a wrapped
 * handler out. This is a linear fold — not a Continuum. PSR-15 middleware
 * differs from Continuum's Progression contract in that middleware MAY
 * short-circuit by returning a response without delegating to the next
 * handler, whereas Continuum requires every Progression to call $next().
 */
final class HttpMiddleware implements RequestHandlerInterface
{
    /**
     * @var list<MiddlewareInterface|class-string<MiddlewareInterface>>
     */
    private array $middleware = [];

    public function __construct(
        private RequestHandlerInterface $handler,
        private ContainerInterface $container,
    ) {
    }

    /**
     * Add a middleware to the stack.
     *
     * Middleware is executed in the order it is added — the first middleware
     * added is the outermost layer of the onion.
     *
     * @param MiddlewareInterface|class-string<MiddlewareInterface> $middleware
     */
    public function add(MiddlewareInterface|string $middleware): self
    {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->middleware === []) {
            return $this->handler->handle($request);
        }

        $pipeline = new Pipeline();

        foreach (array_reverse($this->middleware) as $middleware) {
            $resolved = is_string($middleware)
                ? $this->resolveMiddleware($middleware)
                : $middleware;

            $pipeline->pipe(new MiddlewareStage($resolved));
        }

        /** @var RequestHandlerInterface $handler */
        $handler = $pipeline->send($this->handler);

        return $handler->handle($request);
    }

    /**
     * Resolve a middleware class-string from the container.
     *
     * @param class-string<MiddlewareInterface> $middleware
     */
    private function resolveMiddleware(string $middleware): MiddlewareInterface
    {
        $resolved = $this->container->get($middleware);

        if (!$resolved instanceof MiddlewareInterface) {
            throw new \RuntimeException(
                sprintf('Middleware "%s" does not implement %s.', $middleware, MiddlewareInterface::class)
            );
        }

        return $resolved;
    }
}
