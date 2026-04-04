<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

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

    public function render(mixed $data, string $dtoClass = ''): ResponseInterface
    {
        $markdown = $this->formatter->format($data, $dtoClass);
        return $this->buildResponse($markdown, 'text/markdown; charset=UTF-8');
    }
}
