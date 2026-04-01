<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Glitch\HttpException;
use Psr\Http\Message\ResponseInterface;

/**
 * Renders exceptions as JSON HTTP responses.
 *
 * Composes a JsonResponseRenderer to build the actual response.
 * In debug mode, includes exception class, file, line, and trace.
 */
class JsonExceptionResponseRenderer implements ExceptionRenderer
{
    public function __construct(
        private readonly JsonResponseRenderer $renderer,
        private readonly bool $debug = false,
    ) {
    }

    public function render(\Throwable $e): ResponseInterface
    {
        $status = $e instanceof HttpException
            ? $e->getStatusCode()
            : StatusCode::InternalServerError;

        $payload = [
            'error' => [
                'status' => $status->value,
                'message' => $e->getMessage(),
            ],
        ];

        if ($this->debug) {
            $payload['error']['exception'] = get_class($e);
            $payload['error']['file'] = $e->getFile();
            $payload['error']['line'] = $e->getLine();
            $payload['error']['trace'] = $e->getTrace();
        }

        return $this->renderer->render($payload, status: $status);
    }
}
