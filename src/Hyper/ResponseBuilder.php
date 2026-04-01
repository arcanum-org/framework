<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Psr\Http\Message\ResponseInterface;

/**
 * Shared logic for building an HTTP Response from a string body.
 *
 * Used by response renderers that wrap Shodo formatters —
 * each renderer formats data into a string, then uses this
 * trait to build the Response with Content-Type, Content-Length,
 * and a proper Stream body.
 */
trait ResponseBuilder
{
    protected function buildResponse(
        string $content,
        string $contentType,
        StatusCode $status = StatusCode::OK,
    ): ResponseInterface {
        $body = new Stream(LazyResource::for('php://memory', 'w+'));
        $body->write($content);

        return new Response(
            new Message(
                new Headers([
                    'Content-Type' => [$contentType],
                    'Content-Length' => [(string) strlen($content)],
                ]),
                $body,
                Version::v11,
            ),
            $status,
        );
    }
}
