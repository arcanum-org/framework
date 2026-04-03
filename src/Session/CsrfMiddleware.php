<?php

declare(strict_types=1);

namespace Arcanum\Session;

use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CSRF protection middleware.
 *
 * Validates a CSRF token on state-changing requests (POST, PUT, PATCH, DELETE).
 * The token is read from the `_token` body parameter or the `X-CSRF-TOKEN` header.
 *
 * Requests with a Bearer token in the Authorization header are skipped —
 * API clients authenticate via tokens and don't need CSRF protection.
 *
 * Safe methods (GET, HEAD, OPTIONS) are always allowed through.
 */
final class CsrfMiddleware implements MiddlewareInterface
{
    private const STATE_CHANGING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];
    private const TOKEN_FIELD = '_token';
    private const TOKEN_HEADER = 'X-CSRF-TOKEN';

    public function __construct(private readonly ActiveSession $registry)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        if (!in_array($method, self::STATE_CHANGING_METHODS, true)) {
            return $handler->handle($request);
        }

        // API clients using Bearer tokens don't need CSRF protection.
        // Require a non-empty token — "Bearer " alone is not valid.
        $authorization = $request->getHeaderLine('Authorization');
        if (str_starts_with($authorization, 'Bearer ') && trim(substr($authorization, 7)) !== '') {
            return $handler->handle($request);
        }

        $session = $this->registry->get();
        $token = $this->extractToken($request);

        if ($token === '' || !$session->csrfToken()->matches($token)) {
            throw new HttpException(StatusCode::Forbidden, 'CSRF token mismatch.');
        }

        return $handler->handle($request);
    }

    private function extractToken(ServerRequestInterface $request): string
    {
        // Try the request body first (form submissions).
        $body = $request->getParsedBody();
        if (is_array($body) && isset($body[self::TOKEN_FIELD]) && is_string($body[self::TOKEN_FIELD])) {
            return $body[self::TOKEN_FIELD];
        }

        // Fall back to the header (AJAX requests).
        $header = $request->getHeaderLine(self::TOKEN_HEADER);
        if ($header !== '') {
            return $header;
        }

        return '';
    }
}
