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

    public function format(mixed $data, string $dtoClass = ''): string
    {
        $templatePath = $this->resolver->resolve($dtoClass);

        if ($templatePath === null) {
            return $this->fallback->format($data);
        }

        return $this->renderTemplate($templatePath, $data, $dtoClass);
    }

    /**
     * Compile arbitrary template source and render it.
     *
     * Takes raw template source (not a file path), compiles it through the
     * standard pipeline (directives, helper rewriting, expression passes),
     * and renders with the standard variable setup (escape function, helpers).
     */
    public function renderSlice(
        string $source,
        string $templateDirectory,
        mixed $data,
        string $dtoClass = '',
    ): string {
        $compiled = $this->compiler->compile($source, $templateDirectory);

        $variables = $this->extractVariables($data);
        $this->resolveClosures($variables, $compiled);
        $variables['__escape'] = static fn(string $value): string =>
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $variables['__helpers'] = $this->helpers !== null
            ? $this->helpers->for($dtoClass) : [];

        return $this->execute($compiled, $variables);
    }

    /**
     * Render a specific element by its id attribute from a template.
     *
     * Extracts the content section (no layout), finds the element with the
     * given id in the raw template source, and compiles/renders only that
     * element (outerHTML — includes the element's own tags).
     *
     * Falls back to rendering the full content section with a log warning
     * when the id isn't found in the template.
     *
     * Uses the fragment-keyed cache (template path + element id) so each
     * element compiles and caches independently.
     */
    public function renderElementById(
        string $id,
        mixed $data,
        string $dtoClass = '',
    ): string {
        $templatePath = $this->resolver->resolve($dtoClass);

        if ($templatePath === null) {
            return $this->fallback->format($data);
        }

        if ($this->cache->isFresh($templatePath, $id)) {
            $compiled = $this->cache->load($templatePath, $id);
        } else {
            $source = $this->reader->read($templatePath);

            // Extract the content section first (no layout wrapper).
            $contentSource = $this->compiler->compile(
                $source,
                dirname($templatePath),
                fragment: true,
            );

            $extraction = $this->compiler->extractElementById(
                $contentSource,
                $id,
            );

            if ($extraction === null) {
                $this->logger?->warning(
                    'Element with id "{id}" not found in template "{template}"'
                        . ' — falling back to content section.',
                    ['id' => $id, 'template' => $templatePath],
                );

                return $this->renderContentSection($templatePath, $source, $data, $dtoClass);
            }

            $compiled = $this->compiler->compile($extraction->outerHtml);
            $this->cache->store(
                $templatePath,
                $compiled,
                $this->compiler->lastDependencies(),
                $id,
            );
        }

        $variables = $this->extractVariables($data);
        $this->resolveClosures($variables, $compiled);
        $variables['__escape'] = static fn(string $value): string =>
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $variables['__helpers'] = $this->helpers !== null
            ? $this->helpers->for($dtoClass) : [];

        return $this->execute($compiled, $variables);
    }

    /**
     * Render the content section of a template (no layout), used as
     * the fall-through when element-by-id extraction fails.
     */
    private function renderContentSection(
        string $templatePath,
        string $source,
        mixed $data,
        string $dtoClass,
    ): string {
        $compiled = $this->compiler->compile(
            $source,
            dirname($templatePath),
            fragment: true,
        );

        $variables = $this->extractVariables($data);
        $this->resolveClosures($variables);
        $variables['__escape'] = static fn(string $value): string =>
            htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $variables['__helpers'] = $this->helpers !== null
            ? $this->helpers->for($dtoClass) : [];

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
        $this->resolveClosures($variables);
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
     * Resolve closure-valued template variables.
     *
     * Handlers can return closures as lazy data suppliers:
     *
     *   return [
     *       'posts'  => fn() => $this->db->model->recentPosts(),
     *       'stats'  => fn() => $this->db->model->expensiveStats(),
     *       'title'  => 'Dashboard',
     *   ];
     *
     * On full renders ($compiledSource is null), all closures are invoked —
     * every variable is needed. On partial renders (element-by-id, slices),
     * only closures whose variable names appear in the compiled source are
     * invoked. Unreferenced closures are removed from the array entirely.
     *
     * Closures must be pure data suppliers — no side effects, no event
     * dispatching. Events belong in the handler, not in lazy data.
     *
     * The scan is text-based: a $variable inside {{ if false }} still
     * triggers invocation. The main win — skipping closures for data in
     * other fragments — is preserved.
     *
     * @param array<string, mixed> $variables
     */
    private function resolveClosures(array &$variables, ?string $compiledSource = null): void
    {
        foreach ($variables as $key => $value) {
            if (!($value instanceof \Closure)) {
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
