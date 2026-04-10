<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Formatters;

use Arcanum\Shodo\Formatter;
use Arcanum\Shodo\HelperResolver;
use Arcanum\Shodo\TemplateEngine;
use Arcanum\Shodo\TemplateResolver;

/**
 * Formats data as an HTML string using co-located templates.
 *
 * Template discovery follows PSR-4 convention: the DTO class name maps
 * to a .html file in the same directory as the class. When no template
 * exists, falls back to a generic HTML representation of the data.
 *
 * The formatter owns data → variable conversion, escape function setup,
 * helper scoping, and closure resolution. The actual template compilation
 * and execution is delegated to TemplateEngine.
 */
class HtmlFormatter implements Formatter
{
    public function __construct(
        private readonly TemplateResolver $resolver,
        private readonly TemplateEngine $engine,
        private readonly HtmlFallbackFormatter $fallback,
        private readonly ?HelperResolver $helpers = null,
    ) {
    }

    /**
     * Resolve the template file path for a DTO class.
     *
     * Returns the absolute path to the co-located template file, or null
     * when no template exists for the given class.
     */
    public function resolveTemplate(string $dtoClass): ?string
    {
        return $this->resolver->resolve($dtoClass);
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
     * Resolve template path with status-specific override.
     *
     * Tries {Dto}.{status}.html first, then {Dto}.html.
     */
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
