<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Formatters;

use Arcanum\Shodo\Formatter;
use Arcanum\Shodo\HelperResolver;
use Arcanum\Shodo\TemplateEngine;
use Arcanum\Shodo\TemplateResolver;

/**
 * Formats data as Markdown using co-located templates.
 *
 * Template discovery follows PSR-4 convention: the DTO class name maps
 * to a .md file in the same directory as the class. When no template
 * exists, falls back to a structured Markdown representation.
 *
 * Uses an identity escape function — no escaping is needed for Markdown.
 */
class MarkdownFormatter implements Formatter
{
    public function __construct(
        private readonly TemplateResolver $resolver,
        private readonly TemplateEngine $engine,
        private readonly MarkdownFallbackFormatter $fallback,
        private readonly ?HelperResolver $helpers = null,
    ) {
    }

    public function format(mixed $data, string $dtoClass = '', int $statusCode = 0): string
    {
        $templatePath = $this->resolveTemplatePath($dtoClass, $statusCode);

        if ($templatePath === null) {
            return $this->fallback->format($data);
        }

        $variables = $this->buildVariables($data, $dtoClass);
        return $this->engine->render($templatePath, $variables);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVariables(mixed $data, string $dtoClass): array
    {
        $variables = $this->extractVariables($data);
        $variables['__escape'] = static fn(string $value): string => $value;
        $variables['__helpers'] = $this->helpers !== null
            ? $this->helpers->for($dtoClass) : [];

        return $variables;
    }

    private function resolveTemplatePath(string $dtoClass, int $statusCode): ?string
    {
        if ($statusCode > 0) {
            $statusPath = $this->resolver->resolveForStatus($dtoClass, $statusCode);
            if ($statusPath !== null) {
                return $statusPath;
            }
        }

        return $this->resolver->resolve($dtoClass);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractVariables(mixed $data): array
    {
        if (is_array($data)) {
            /** @var array<string, mixed> */
            return $data;
        }

        if (is_object($data)) {
            return get_object_vars($data);
        }

        return ['data' => $data];
    }
}
