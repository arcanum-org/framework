<?php

declare(strict_types=1);

namespace Arcanum\Hyper\Event;

use Arcanum\Echo\Event;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched after the response has been sent to the client.
 *
 * Read-only. Fired from HyperKernel::terminate() after
 * fastcgi_finish_request() (if available). Best-effort post-response —
 * use for cleanup, deferred logging, or metrics that don't need to
 * block the response.
 */
class ResponseSent extends Event
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
