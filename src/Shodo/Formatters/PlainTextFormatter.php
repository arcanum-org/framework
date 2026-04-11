<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Formatters;

use Arcanum\Shodo\Formatter;
use Arcanum\Shodo\HelperResolver;
use Arcanum\Shodo\TemplateEngine;

/**
 * Formats data as a plain text string using templates.
 *
 * Uses an identity escape function — no escaping is needed for plain text.
 * When no template path is provided, renders the bundled fallback template.
 */
class PlainTextFormatter implements Formatter
{
    private const FALLBACK_TEMPLATE = __DIR__ . '/../Templates/fallback.txt';

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
     * @return array<string, mixed>
     */
    private function buildVariables(mixed $data, string $dtoClass): array
    {
        $variables = $this->extractVariables($data);
        $variables['__vars'] = array_keys($variables);
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
