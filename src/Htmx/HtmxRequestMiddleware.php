<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Inbound middleware that populates the htmx request context.
 *
 * On every request:
 * 1. Wraps the PSR-7 request in an HtmxRequest decorator
 * 2. Sets it on the HtmxAwareResponseRenderer so the rendering
 *    pipeline knows the request type and target element
 * 3. Auto-adds Vary: HX-Request to the response so HTTP caches
 *    don't serve htmx partial HTML to full-page requests (or vice
 *    versa)
 *
 * Replaces the starter app's App\Http\Middleware\Htmx.
 */
final class HtmxRequestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly HtmxAwareResponseRenderer $renderer,
        private readonly bool $addVaryHeader = true,
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $htmxRequest = new HtmxRequest($request);

        if ($htmxRequest->isHtmx()) {
            $this->renderer->setHtmxRequest($htmxRequest);
        }

        $response = $handler->handle($request);

        if ($this->addVaryHeader) {
            $response = $response->withAddedHeader('Vary', 'HX-Request');
        }

        return $response;
    }
}
