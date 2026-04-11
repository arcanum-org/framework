<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Flow\Stage;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adapts a PSR-15 MiddlewareInterface into a Flow Pipeline Stage.
 *
 * Each stage receives a RequestHandlerInterface (the inner handler) and
 * returns a new RequestHandlerInterface that wraps it with the middleware.
 * This allows the middleware onion to be built as a linear Pipeline fold:
 *
 *     CoreHandler → wrap(C) → wrap(B) → wrap(A) → FullyWrappedHandler
 *
 * The resulting handler, when invoked, executes middleware in the correct
 * onion order (A before → B before → C before → core → C after → B after → A after).
 */
final class MiddlewareStage implements Stage
{
    public function __construct(private MiddlewareInterface $middleware)
    {
    }

    public function __invoke(object $payload): RequestHandlerInterface
    {
        if (!$payload instanceof RequestHandlerInterface) {
            throw new \InvalidArgumentException(sprintf(
                'MiddlewareStage expects a %s payload, got %s.',
                RequestHandlerInterface::class,
                get_class($payload),
            ));
        }

        $middleware = $this->middleware;
        $inner = $payload;

        return new CallableHandler(
            static fn(ServerRequestInterface $request): ResponseInterface =>
                $middleware->process($request, $inner)
        );
    }
}
