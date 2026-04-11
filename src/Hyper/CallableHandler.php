<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adapts a Closure into a PSR-15 RequestHandlerInterface.
 *
 * Used internally by HyperKernel to wrap its protected handleRequest()
 * method as a handler that HttpMiddleware can dispatch to.
 */
final class CallableHandler implements RequestHandlerInterface
{
    /**
     * @param \Closure(ServerRequestInterface): ResponseInterface $callback
     */
    public function __construct(private \Closure $callback)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->callback)($request);
    }
}
