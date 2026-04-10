<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Parchment\Reader;
use Psr\Log\LoggerInterface;

/**
 * Compiles, caches, and executes Shodo templates.
 *
 * The engine owns the mechanical steps of template rendering:
 * read source → compile → cache → execute with variables. It does NOT
 * own template resolution (TemplateResolver), data extraction, escape
 * functions, or helper scoping — those are the caller's responsibility.
 *
 * Variables passed to render methods must include any __escape and
 * __helpers entries the template expects. The engine treats the
 * variables array as opaque.
 */
final class TemplateEngine
{
    public function __construct(
        private readonly TemplateCompiler $compiler,
        private readonly TemplateCache $cache,
        private readonly Reader $reader = new Reader(),
        private readonly bool $debug = false,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Render a template file with the given variables.
     *
     * Uses the compiled cache when available.
     *
     * @param array<string, mixed> $variables
     */
    public function render(string $templatePath, array $variables): string
    {
        if ($this->cache->isFresh($templatePath)) {
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

        if ($this->debug) {
            $source ??= $this->reader->read($templatePath);
            TemplateAnalyzer::warnUnused($source, $variables, $templatePath);
        }

        // Full render — all closures needed.
        $this->resolveClosures($variables);
        return $this->execute($compiled, $variables);
    }

    /**
     * Render only the content section of a template (no layout wrapper).
     *
     * Bypasses the compiled cache since fragment output differs from
     * the full render.
     *
     * @param array<string, mixed> $variables
     */
    public function renderFragment(string $templatePath, array $variables): string
    {
        $source = $this->reader->read($templatePath);
        $compiled = $this->compiler->compileFragment(
            $source,
            dirname($templatePath),
        );

        // Fragment — all closures needed (content section may use any variable).
        $this->resolveClosures($variables);
        return $this->execute($compiled, $variables);
    }

    /**
     * Render a specific element by its id attribute from a template.
     *
     * Extracts the content section (no layout), finds the element with
     * the given id, and compiles/renders only that element (outerHTML).
     *
     * Falls back to rendering the full content section when the id
     * isn't found in the template.
     *
     * @param array<string, mixed> $variables
     */
    public function renderElement(
        string $templatePath,
        string $elementId,
        array $variables,
    ): string {
        if ($this->cache->isFresh($templatePath, $elementId)) {
            $compiled = $this->cache->load($templatePath, $elementId);
        } else {
            $source = $this->reader->read($templatePath);

            // Extract the content section first (no layout wrapper).
            $contentSource = $this->compiler->compileFragment(
                $source,
                dirname($templatePath),
            );

            $extraction = $this->compiler->extractElementById(
                $contentSource,
                $elementId,
            );

            if ($extraction === null) {
                $this->logger?->warning(
                    'Element with id "{id}" not found in template "{template}"'
                        . ' — falling back to content section.',
                    ['id' => $elementId, 'template' => $templatePath],
                );

                // Fall back to full content section.
                $this->resolveClosures($variables);
                return $this->execute($contentSource, $variables);
            }

            $compiled = $this->compiler->compile($extraction->outerHtml);
            $this->cache->store(
                $templatePath,
                $compiled,
                $this->compiler->lastDependencies(),
                $elementId,
            );
        }

        // Partial render — only invoke closures referenced in compiled output.
        $this->resolveClosures($variables, $compiled);
        return $this->execute($compiled, $variables);
    }

    /**
     * Compile arbitrary template source and render it.
     *
     * Takes raw template source (not a file path), compiles it through
     * the standard pipeline, and executes with the given variables.
     *
     * @param array<string, mixed> $variables
     */
    public function renderSource(
        string $source,
        string $baseDirectory,
        array $variables,
    ): string {
        $compiled = $this->compiler->compile($source, $baseDirectory);
        // Partial render — only invoke closures referenced in compiled output.
        $this->resolveClosures($variables, $compiled);
        return $this->execute($compiled, $variables);
    }

    /**
     * Resolve closure-valued template variables.
     *
     * When $compiledSource is null (full renders), all closures are invoked.
     * When provided (partial renders), only closures whose variable names
     * appear in the compiled source are invoked; unreferenced closures are
     * removed entirely.
     *
     * @param array<string, mixed> $variables
     */
    private function resolveClosures(array &$variables, ?string $compiledSource = null): void
    {
        foreach ($variables as $key => $value) {
            if (!($value instanceof \Closure) || str_starts_with($key, '__')) {
                continue;
            }

            if ($compiledSource === null || str_contains($compiledSource, '$' . $key)) {
                $variables[$key] = $value();
            } else {
                unset($variables[$key]);
            }
        }
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
