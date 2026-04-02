<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Formatters;

use Arcanum\Parchment\Reader;
use Arcanum\Shodo\Formatter;
use Arcanum\Shodo\Helper\HelperResolver;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateResolver;

/**
 * Formats data as an HTML string using co-located templates.
 *
 * Template discovery follows PSR-4 convention: the DTO class name maps
 * to a .html file in the same directory as the class. When no template
 * exists, falls back to a generic HTML representation of the data.
 */
class HtmlFormatter implements Formatter
{
    public function __construct(
        private readonly TemplateResolver $resolver,
        private readonly TemplateCompiler $compiler,
        private readonly TemplateCache $cache,
        private readonly HtmlFallback $fallback,
        private readonly Reader $reader = new Reader(),
        private readonly ?HelperResolver $helpers = null,
    ) {
    }

    public function format(mixed $data, string $dtoClass = ''): string
    {
        $templatePath = $this->resolver->resolve($dtoClass);

        if ($templatePath === null) {
            return $this->fallback->render($data);
        }

        return $this->renderTemplate($templatePath, $data, $dtoClass);
    }

    private function renderTemplate(string $templatePath, mixed $data, string $dtoClass): string
    {
        if ($this->cache->isFresh($templatePath)) {
            $compiled = $this->cache->load($templatePath);
        } else {
            $source = $this->reader->read($templatePath);
            $compiled = $this->compiler->compile($source);
            $this->cache->store($templatePath, $compiled);
        }

        $variables = $this->extractVariables($data);
        $variables['__escape'] = static fn(string $value): string =>
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $variables['__helpers'] = $this->helpers !== null ? $this->helpers->for($dtoClass) : [];

        return $this->execute($compiled, $variables);
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

    /**
     * @param array<string, mixed> $variables
     */
    private function execute(string $compiledPhp, array $variables): string
    {
        // Use a static closure to prevent $this leakage into template scope.
        $executor = static function (string $__compiled, array $__vars): string {
            extract($__vars);
            ob_start();
            eval('?>' . $__compiled);
            return (string) ob_get_clean();
        };

        return $executor($compiledPhp, $variables);
    }
}
