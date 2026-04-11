<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Flow\River\EmptyStream;
use Psr\Http\Message\ResponseInterface;

/**
 * Renders an empty HTTP response with a given status code.
 *
 * Used for CQRS command responses where no body is needed:
 * void → 204 No Content, DTO → 201 Created, null → 202 Accepted.
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
