<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Flow\Conveyor\EmptyDTO;
use Arcanum\Flow\River\EmptyStream;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Psr\Http\Message\ResponseInterface;

/**
 * Builds HTTP responses for Command handler results.
 *
 * Commands have no response body by default. The status code
 * communicates the outcome:
 *
 *   - EmptyDTO (void handler) → 204 No Content
 *   - Any other value         → 201 Created
 */
final class CommandResponseBuilder
{
    public function build(object $result): ResponseInterface
    {
        $status = $result instanceof EmptyDTO
            ? StatusCode::NoContent
            : StatusCode::Created;

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
