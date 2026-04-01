<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Shodo\Renderer;
use Psr\Http\Message\ResponseInterface;

/**
 * Base class for HTTP response renderers that wrap Shodo formatters.
 *
 * Provides the shared logic for building a ResponseInterface from a
 * formatted string body — Content-Type, Content-Length, Stream wrapping.
 * Subclasses compose a specific Formatter and call buildResponse().
 */
abstract class ResponseRenderer implements Renderer
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
