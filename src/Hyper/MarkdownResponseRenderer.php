<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Hourglass\Stopwatch;
use Arcanum\Shodo\Formatters\MarkdownFormatter;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP response adapter for Markdown output.
 *
 * Composes a MarkdownFormatter for data → string conversion, then wraps
 * the result in a ResponseInterface with text/markdown content type.
 */
class MarkdownResponseRenderer extends ResponseRenderer
{
    public function __construct(
        private readonly MarkdownFormatter $formatter,
    ) {
    }

    public function render(
        mixed $data,
        string $dtoClass = '',
        StatusCode $status = StatusCode::OK,
    ): ResponseInterface {
        Stopwatch::tap('render.start');
        try {
            $markdown = $this->formatter->format($data, $dtoClass, $status->value);
            return $this->buildResponse($markdown, 'text/markdown; charset=UTF-8', $status);
        } finally {
            Stopwatch::tap('render.complete');
        }
    }
}
