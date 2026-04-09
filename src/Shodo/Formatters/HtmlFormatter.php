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
use Psr\Log\LoggerInterface;

/**
 * Formats data as an HTML string using co-located templates.
 *
 * Template discovery follows PSR-4 convention: the DTO class name maps
 * to a .html file in the same directory as the class. When no template
 * exists, falls back to a generic HTML representation of the data.
 */
class HtmlFormatter implements Formatter
{
    private bool $fragment = false;

    public function __construct(
        private readonly TemplateResolver $resolver,
        private readonly TemplateCompiler $compiler,
        private readonly TemplateCache $cache,
        private readonly HtmlFallbackFormatter $fallback,
        private readonly Reader $reader = new Reader(),
        private readonly ?HelperResolver $helpers = null,
        private readonly bool $debug = false,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Enable fragment mode for the current request.
     *
     * When enabled, templates that use @extends will render only the
     * 'content' section without the layout wrapper. Call this from
     * middleware when the HX-Request header is present.
     */
    public function setFragment(bool $fragment): void
    {
        $this->fragment = $fragment;
    }

    public function format(mixed $data, string $dtoClass = ''): string
    {
        $templatePath = $this->resolver->resolve($dtoClass);

        if ($templatePath === null) {
            return $this->fallback->format($data);
        }

        return $this->renderTemplate($templatePath, $data, $dtoClass);
    }

    /**
     * Render a named fragment from a template.
     *
     * Returns just the rendered fragment — no layout, no surrounding section.
     * When the fragment name doesn't exist in the template, logs a warning
     * and falls back to rendering the content section (fragment mode).
     *
     * Uses the same template-resolver, dependency-resolution, and cache
     * paths as the full render.
     */
    public function renderFragment(string $name, mixed $data, string $dtoClass = ''): string
    {
        $templatePath = $this->resolver->resolve($dtoClass);

        if ($templatePath === null) {
            return $this->fallback->format($data);
        }

        return $this->renderNamedFragment($templatePath, $name, $data, $dtoClass);
    }

    private function renderNamedFragment(
        string $templatePath,
        string $fragmentName,
        mixed $data,
        string $dtoClass,
    ): string {
        // Try the named fragment first, with caching.
        if ($this->cache->isFresh($templatePath, $fragmentName)) {
            $compiled = $this->cache->load($templatePath, $fragmentName);
        } else {
            $source = $this->reader->read($templatePath);
            $compiled = $this->compiler->compile(
                $source,
                dirname($templatePath),
                fragmentName: $fragmentName,
            );

            $this->cache->store(
                $templatePath,
                $compiled,
                $this->compiler->lastDependencies(),
                $fragmentName,
            );
        }

        // Empty compiled output means the fragment name wasn't found.
        // Fall back to rendering the content section and log a warning.
        if ($compiled === '') {
            $this->logger?->warning(
                'Fragment "{fragment}" not found in template "{template}"'
                    . ' — falling back to content section.',
                ['fragment' => $fragmentName, 'template' => $templatePath],
            );

            $source = $source ?? $this->reader->read($templatePath);
            $compiled = $this->compiler->compile(
                $source,
                dirname($templatePath),
                fragment: true,
            );
        }

        $variables = $this->extractVariables($data);
        $variables['__escape'] = static fn(string $value): string =>
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $variables['__helpers'] = $this->helpers !== null ? $this->helpers->for($dtoClass) : [];

        return $this->execute($compiled, $variables);
    }

    private function renderTemplate(string $templatePath, mixed $data, string $dtoClass): string
    {
        if ($this->fragment) {
            // Fragment mode bypasses cache — different output than full render.
            $source = $this->reader->read($templatePath);
            $compiled = $this->compiler->compile(
                $source,
                dirname($templatePath),
                fragment: true,
            );
        } elseif ($this->cache->isFresh($templatePath)) {
            $compiled = $this->cache->load($templatePath);
        } else {
            $source = $this->reader->read($templatePath);
            $compiled = $this->compiler->compile(
                $source,
                dirname($templatePath),
            );
            $this->cache->store(
                $templatePath,
                $compiled,
                $this->compiler->lastDependencies(),
            );
        }

        $variables = $this->extractVariables($data);
        $variables['__escape'] = static fn(string $value): string =>
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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

        // Use a static closure to prevent $this leakage into template scope.
        $executor = static function (string $__compiled, array $__vars) use ($debug, $availableKeys): string {
            if ($debug) {
                set_error_handler(static function (int $severity, string $message) use ($availableKeys): never {
                    // Extract variable name from "Undefined variable $foo"
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
