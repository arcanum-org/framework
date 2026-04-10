<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Formatters;

use Arcanum\Shodo\Formatter;
use Arcanum\Shodo\HelperResolver;
use Arcanum\Shodo\TemplateEngine;

/**
 * Formats data as an HTML string using templates.
 *
 * The formatter owns data → variable conversion, escape function setup,
 * and helper scoping. Template resolution is the caller's responsibility
 * — the formatter receives a pre-resolved path. When no path is provided,
 * falls back to a generic HTML representation of the data.
 *
 * The actual template compilation and execution is delegated to
 * TemplateEngine.
 */
class HtmlFormatter implements Formatter
{
    public function __construct(
        private readonly TemplateEngine $engine,
        private readonly HtmlFallbackFormatter $fallback,
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
     * Build the template variable array from handler data.
     *
     * Extracts variables from the data and adds the escape function
     * and scoped helpers. Closure-valued variables are left unresolved
     * — the TemplateEngine resolves them selectively based on which
     * variables the compiled template actually references.
     *
     * @return array<string, mixed>
     */
    public function buildVariables(mixed $data, string $dtoClass = ''): array
    {
        $variables = $this->extractVariables($data);
        $variables['__escape'] = static fn(string $value): string =>
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
