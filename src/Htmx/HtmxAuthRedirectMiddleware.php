<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handles auth redirects for htmx requests.
 *
 * When a 401 or 403 response is returned for an htmx request, the normal
 * redirect-to-login pattern doesn't work — htmx would swap the login
 * page HTML into whatever element triggered the request. Instead, this
 * middleware intercepts the error response and returns an HX-Location
 * header (or HX-Refresh) that tells htmx to perform a full client-side
 * navigation to the login page.
 *
 * Non-htmx requests pass through unchanged.
 */
final class HtmxAuthRedirectMiddleware implements MiddlewareInterface
{
    /**
     * @param string $loginUrl    URL to redirect to on 401/403
     * @param bool   $useRefresh  When true, sends HX-Refresh instead
     *                            of HX-Location (forces a full page reload)
     */
    public function __construct(
        private readonly string $loginUrl = '/login',
        private readonly bool $useRefresh = false,
    ) {
    }

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);

        if (!$request->hasHeader('HX-Request')) {
            return $response;
        }

        $status = $response->getStatusCode();

        if ($status !== 401 && $status !== 403) {
            return $response;
        }

        $builder = new HtmxResponse($response);

        if ($this->useRefresh) {
            return $builder->withRefresh()->toResponse();
        }

        return $builder->withLocation($this->loginUrl)->toResponse();
    }
}
