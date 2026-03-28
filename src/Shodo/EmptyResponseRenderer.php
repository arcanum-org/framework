<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Flow\River\EmptyStream;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Psr\Http\Message\ResponseInterface;

/**
 * Renders an empty HTTP response with a given status code.
 */
final class EmptyResponseRenderer
{
    public function render(StatusCode $status = StatusCode::NoContent): ResponseInterface
    {
        return new Response(
            new Message(
                new Headers([]),
                new EmptyStream(),
                Version::v11,
            ),
            $status,
        );
    }
}
