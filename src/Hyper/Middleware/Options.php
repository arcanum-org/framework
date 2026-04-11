<?php

declare(strict_types=1);

namespace Arcanum\Hyper\Middleware;

use Arcanum\Atlas\HttpRouter;
use Arcanum\Flow\River\EmptyStream;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Handles OPTIONS requests automatically.
 *
 * Intercepts OPTIONS requests and returns 204 No Content with an Allow
 * header listing the HTTP methods available for the requested path.
 * Returns 404 if the path doesn't resolve to any route.
 *
 * This middleware is registered as the innermost layer by the framework,
 * so app middleware (e.g., CORS) can add headers on the way out.
 */
final class Options implements MiddlewareInterface
{
    public function __construct(private HttpRouter $router)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (strtoupper($request->getMethod()) !== 'OPTIONS') {
            return $handler->handle($request);
        }

        $path = $request->getUri()->getPath();
        $allowed = $this->router->allowedMethods($path);

        if ($allowed === []) {
            return $handler->handle($request);
        }

        // Always include OPTIONS itself in the Allow header.
        $allowed[] = 'OPTIONS';

        return new Response(
            new Message(
                new Headers(['Allow' => [implode(', ', $allowed)]]),
                new EmptyStream(),
                Version::v11,
            ),
            StatusCode::NoContent,
        );
    }
}
