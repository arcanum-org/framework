<?php

declare(strict_types=1);

namespace Arcanum\Hyper\Event;

use Arcanum\Echo\Event;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched when a request enters the kernel, before middleware.
 *
 * Mutable: listeners can replace the request via setRequest() to add
 * attributes (e.g., start time, request ID) that flow through the
 * entire middleware and handler chain.
 */
class RequestReceived extends Event
{
    public function __construct(
        private ServerRequestInterface $request,
    ) {
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * Replace the request. The kernel uses the updated request
     * for middleware and handler dispatch.
     */
    public function setRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }
}
