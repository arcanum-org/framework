<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

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

    public function render(mixed $data, string $dtoClass = ''): ResponseInterface
    {
        $text = $this->formatter->format($data, $dtoClass);
        return $this->buildResponse($text, 'text/plain; charset=UTF-8');
    }
}
