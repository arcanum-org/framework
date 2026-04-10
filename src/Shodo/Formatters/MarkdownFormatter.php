<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Formatters;

use Arcanum\Shodo\Formatter;
use Arcanum\Shodo\HelperResolver;
use Arcanum\Shodo\TemplateEngine;

/**
 * Formats data as Markdown using templates.
 *
 * Uses an identity escape function — no escaping is needed for Markdown.
 * When no template path is provided, falls back to a structured
 * Markdown representation.
 */
class MarkdownFormatter implements Formatter
{
    public function __construct(
        private readonly TemplateEngine $engine,
        private readonly MarkdownFallbackFormatter $fallback,
        private readonly ?HelperResolver $helpers = null,
    ) {
    }

    public function format(mixed $data, string $templatePath = '', string $dtoClass = ''): string
    {
        if ($templatePath === '') {
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
