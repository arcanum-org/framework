<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Outbound middleware that projects domain events into HX-Trigger headers.
 *
 * Reads ClientBroadcast events captured by EventCapture during the
 * handler pass and merges them into the HX-Trigger response header.
 *
 * Also copies Location headers to HX-Location for htmx requests,
 * so command redirects (201 Created + Location) work without
 * special-casing in the handler.
 *
 * No-ops for non-htmx requests and when no events were captured.
 */
final class HtmxEventTriggerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly EventCapture $capture,
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

        // Copy Location → HX-Location for command redirects.
        if ($response->hasHeader('Location')) {
            $response = $response->withHeader(
                'HX-Location',
                $response->getHeaderLine('Location'),
            );
        }

        // Merge captured ClientBroadcast events into HX-Trigger headers.
        $events = $this->capture->drain();

        if ($events === []) {
            return $response;
        }

        $builder = new HtmxResponse($response);

        foreach ($events as $event) {
            $builder = $builder->withTrigger(
                $event->eventName(),
                $event->payload(),
            );
        }

        return $builder->toResponse();
    }
}
