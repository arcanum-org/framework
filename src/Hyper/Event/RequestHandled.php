<?php

declare(strict_types=1);

namespace Arcanum\Hyper\Event;

use Arcanum\Echo\Event;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched after middleware and handler have produced a response.
 *
 * Read-only: middleware is the last word on the response. Listeners
 * observe but cannot modify. Use for logging, metrics, audit trails.
 */
class RequestHandled extends Event
{
    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly ResponseInterface $response,
    ) {
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
