<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Hourglass\Stopwatch;
use Arcanum\Shodo\Formatters\HtmlFormatter;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP response adapter for HTML output.
 *
 * Composes an HtmlFormatter for data → string conversion, then wraps
 * the result in a ResponseInterface with text/html content type.
 */
class HtmlResponseRenderer extends ResponseRenderer
{
    public function __construct(
        private readonly HtmlFormatter $formatter,
    ) {
    }

    public function render(mixed $data, string $dtoClass = ''): ResponseInterface
    {
        Stopwatch::tap('render.start');
        try {
            $html = $this->formatter->format($data, $dtoClass);
            return $this->buildResponse($html, 'text/html; charset=UTF-8');
        } finally {
            Stopwatch::tap('render.complete');
        }
    }
}
