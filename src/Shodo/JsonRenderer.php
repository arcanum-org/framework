<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Psr\Http\Message\ResponseInterface;

class JsonRenderer implements Renderer
{
    /**
     * Render data as a JSON HTTP response.
     */
    public function render(mixed $data, StatusCode $status = StatusCode::OK): ResponseInterface
    {
        $json = json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);

        $body = new Stream(LazyResource::for('php://memory', 'w+'));
        $body->write($json);

        return new Response(
            new Message(
                new Headers([
                    'Content-Type' => ['application/json'],
                    'Content-Length' => [(string) strlen($json)],
                ]),
                $body,
                Version::v11,
            ),
            $status,
        );
    }
}
