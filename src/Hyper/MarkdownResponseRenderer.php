<?php

declare(strict_types=1);

namespace Arcanum\Hyper;

use Arcanum\Hourglass\Stopwatch;
use Arcanum\Shodo\Formatters\MarkdownFormatter;
use Arcanum\Shodo\TemplateResolver;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP response adapter for Markdown output.
 *
 * Resolves the template path, then delegates to MarkdownFormatter for
 * data → string conversion, wrapping the result in a ResponseInterface
 * with text/markdown content type.
 */
class MarkdownResponseRenderer extends ResponseRenderer
{
    public function __construct(
        private readonly MarkdownFormatter $formatter,
        private readonly TemplateResolver $resolver,
    ) {
    }

    public function render(
        mixed $data,
        string $dtoClass = '',
        StatusCode $status = StatusCode::OK,
    ): ResponseInterface {
        Stopwatch::tap('render.start');
        try {
            $templatePath = $this->resolveTemplatePath($dtoClass, $status->value);
            $markdown = $this->formatter->format($data, $templatePath, $dtoClass);
            return $this->buildResponse($markdown, 'text/markdown; charset=UTF-8', $status);
        } finally {
            Stopwatch::tap('render.complete');
        }
    }

    private function resolveTemplatePath(string $dtoClass, int $statusCode): string
    {
        if ($statusCode > 0 && $statusCode !== 200) {
            $path = $this->resolver->resolveForStatus($dtoClass, $statusCode);
            if ($path !== null) {
                return $path;
            }
        }

        return $this->resolver->resolve($dtoClass) ?? '';
    }
}
