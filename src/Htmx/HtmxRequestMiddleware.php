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
 * 1. Intercepts /_htmx/csrf.js and serves the CSRF JS shim directly
 * 2. Wraps the PSR-7 request in an HtmxRequest decorator
 * 3. Sets it on the HtmxAwareResponseRenderer so the rendering
 *    pipeline knows the request type and target element
 * 4. Auto-adds Vary: HX-Request to the response so HTTP caches
 *    don't serve htmx partial HTML to full-page requests (or vice
 *    versa)
 *
 * Replaces the starter app's App\Http\Middleware\Htmx.
 */
final class HtmxRequestMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly HtmxAwareResponseRenderer $renderer,
        private readonly HtmxCsrfController $csrfController,
        private readonly bool $addVaryHeader = true,
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        // Serve the CSRF JS shim at /_htmx/csrf.js — handled here
        // so it bypasses routing entirely.
        if ($request->getUri()->getPath() === '/_htmx/csrf.js') {
            return $this->csrfController->handle();
        }

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
