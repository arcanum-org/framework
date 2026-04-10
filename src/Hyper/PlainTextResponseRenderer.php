<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Hourglass\Stopwatch;
use Arcanum\Shodo\Formatters\PlainTextFormatter;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP response adapter for plain text output.
 *
 * Composes a PlainTextFormatter for data → string conversion, then wraps
 * the result in a ResponseInterface with text/plain content type.
 */
class PlainTextResponseRenderer extends ResponseRenderer
{
    public function __construct(
        private readonly PlainTextFormatter $formatter,
    ) {
    }

    public function render(
        mixed $data,
        string $dtoClass = '',
        StatusCode $status = StatusCode::OK,
    ): ResponseInterface {
        Stopwatch::tap('render.start');
        try {
            $text = $this->formatter->format($data, $dtoClass, $status->value);
            return $this->buildResponse($text, 'text/plain; charset=UTF-8', $status);
        } finally {
            Stopwatch::tap('render.complete');
        }
    }
}
