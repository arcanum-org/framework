<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Shodo\HtmlFormatter;
use Arcanum\Shodo\Renderer;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP response adapter for HTML output.
 *
 * Composes an HtmlFormatter for data → string conversion, then wraps
 * the result in a ResponseInterface with text/html content type.
 */
class HtmlResponseRenderer implements Renderer
{
    use ResponseBuilder;

    public function __construct(
        private readonly HtmlFormatter $formatter,
    ) {
    }

    public function render(mixed $data, string $dtoClass = ''): ResponseInterface
    {
        $html = $this->formatter->format($data, $dtoClass);
        return $this->buildResponse($html, 'text/html; charset=UTF-8');
    }
}
