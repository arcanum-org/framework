<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Glitch\ArcanumException;
use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Glitch\HttpException;
use Psr\Http\Message\ResponseInterface;

/**
 * Renders exceptions as JSON HTTP responses.
 *
 * Composes a JsonResponseRenderer to build the actual response.
 * In debug mode, includes exception class, file, line, and trace.
 * When verbose_errors is enabled, includes suggestion from ArcanumException.
 *
 * Output shape is forward-compatible with RFC 9457 Problem Details:
 * `title` maps to RFC `title`, `message` maps to `detail`.
 */
class JsonExceptionResponseRenderer implements ExceptionRenderer
{
    public function __construct(
        private readonly JsonResponseRenderer $renderer,
        private readonly bool $debug = false,
        private readonly bool $verboseErrors = false,
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

        if ($e instanceof ArcanumException) {
            $payload['error']['title'] = $e->getTitle();

            if ($this->verboseErrors && $e->getSuggestion() !== null) {
                $payload['error']['suggestion'] = $e->getSuggestion();
            }
        }

        if ($this->debug) {
            $payload['error']['exception'] = get_class($e);
            $payload['error']['file'] = $e->getFile();
            $payload['error']['line'] = $e->getLine();
            $payload['error']['trace'] = $e->getTrace();
        }

        return $this->renderer->render($payload, status: $status);
    }
}
