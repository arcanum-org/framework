<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Hourglass\Stopwatch;
use Arcanum\Shodo\Formatters\JsonFormatter;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP response adapter for JSON output.
 *
 * Composes a JsonFormatter for data → string conversion, then wraps
 * the result in a ResponseInterface with application/json content type.
 */
class JsonResponseRenderer extends ResponseRenderer
{
    public function __construct(
        private readonly JsonFormatter $formatter = new JsonFormatter(),
    ) {
    }

    public function render(mixed $data, string $dtoClass = '', StatusCode $status = StatusCode::OK): ResponseInterface
    {
        Stopwatch::tap('render.start');
        try {
            $json = $this->formatter->format($data, $dtoClass);
            return $this->buildResponse($json, 'application/json', $status);
        } finally {
            Stopwatch::tap('render.complete');
        }
    }
}
