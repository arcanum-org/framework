<?php

declare(strict_types=1);

namespace Arcanum\Hyper\Event;

use Arcanum\Echo\Event;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Dispatched when an exception is thrown during request handling.
 *
 * Read-only and observational: Glitch still handles exception rendering.
 * Listeners are for reporting, metrics, and notifications.
 */
class RequestFailed extends Event
{
    public function __construct(
        private readonly ServerRequestInterface $request,
        private readonly \Throwable $exception,
    ) {
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }
}
