<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Formatters;

use Arcanum\Parchment\Reader;
use Arcanum\Shodo\Formatter;
use Arcanum\Shodo\HelperResolver;
use Arcanum\Shodo\TemplateAnalyzer;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
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
        private readonly TemplateCompiler $compiler,
        private readonly TemplateCache $cache,
        private readonly MarkdownFallbackFormatter $fallback,
        private readonly Reader $reader = new Reader(),
        private readonly ?HelperResolver $helpers = null,
        private readonly bool $debug = false,
    ) {
    }

    public function format(mixed $data, string $dtoClass = ''): string
    {
        $templatePath = $this->resolver->resolve($dtoClass);

        if ($templatePath === null) {
            return $this->fallback->format($data);
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
        $variables['__escape'] = static fn(string $value): string => $value;
        $variables['__helpers'] = $this->helpers !== null ? $this->helpers->for($dtoClass) : [];

        if ($this->debug) {
            $source = $this->reader->read($templatePath);
            TemplateAnalyzer::warnUnused($source, $variables, $templatePath);
        }

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
        $debug = $this->debug;
        $availableKeys = $debug ? array_keys($variables) : [];

        $executor = static function (string $__compiled, array $__vars) use ($debug, $availableKeys): string {
            if ($debug) {
                set_error_handler(static function (int $severity, string $message) use ($availableKeys): never {
                    if (preg_match('/Undefined variable \\$?(\w+)/', $message, $matches)) {
                        $hint = $availableKeys !== []
                            ? ' Available variables: ' . implode(', ', $availableKeys) . '.'
                            : '';
                        throw new \RuntimeException(sprintf(
                            'Undefined template variable "$%s".%s',
                            $matches[1],
                            $hint,
                        ));
                    }
                    throw new \RuntimeException($message);
                }, \E_WARNING | \E_NOTICE);
            }

            extract($__vars);
            ob_start();

            try {
                eval('?>' . $__compiled);
                return (string) ob_get_clean();
            } catch (\Throwable $__e) {
                ob_end_clean();
                throw $__e;
            } finally {
                if ($debug) {
                    restore_error_handler();
                }
            }
        };

        return $executor($compiledPhp, $variables);
    }
}
