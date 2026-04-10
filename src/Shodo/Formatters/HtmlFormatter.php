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
 * renders the bundled fallback template through the same engine.
 *
 * The actual template compilation and execution is delegated to
 * TemplateEngine.
 */
class HtmlFormatter implements Formatter
{
    private const FALLBACK_TEMPLATE = __DIR__ . '/../Templates/fallback.html';

    public function __construct(
        private readonly TemplateEngine $engine,
        private readonly ?HelperResolver $helpers = null,
    ) {
    }

    public function format(mixed $data, string $templatePath = '', string $dtoClass = ''): string
    {
        $path = $templatePath !== '' ? $templatePath : self::FALLBACK_TEMPLATE;
        $variables = $this->buildVariables($data, $dtoClass);
        return $this->engine->render($path, $variables);
    }

    /**
     * Build the template variable array from handler data.
     *
     * Extracts variables from the data and adds the escape function,
     * scoped helpers, and `$__vars` (list of user data keys for the
     * fallback template's variable-variable iteration). Closure-valued
     * variables are left unresolved — the TemplateEngine resolves them
     * selectively based on which variables the compiled template
     * actually references.
     *
     * @return array<string, mixed>
     */
    public function buildVariables(mixed $data, string $dtoClass = ''): array
    {
        $variables = $this->extractVariables($data);
        $variables['__vars'] = array_keys($variables);
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
