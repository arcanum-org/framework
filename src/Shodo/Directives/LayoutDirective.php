<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Directives;

use Arcanum\Shodo\CompilerContext;
use Arcanum\Shodo\CompilerDirective;

/**
 * Resolves layout inheritance: {{ extends }}, {{ section }}, {{ endsection }}, {{ yield }}.
 *
 * When a template starts with {{ extends 'layout' }}, extracts sections
 * from the child and replaces {{ yield 'name' }} placeholders in the
 * layout. In fragment mode, returns only the 'content' section without
 * the layout wrapper.
 */
final class LayoutDirective implements CompilerDirective
{
    public function keywords(): array
    {
        return ['extends', 'section', 'endsection', 'yield'];
    }

    public function priority(): int
    {
        return 200;
    }

    public function process(string $source, CompilerContext $context): string
    {
        if ($context->templateDirectory === '') {
            return $source;
        }

        return $context->fragment
            ? $this->resolveFragment($source, $context)
            : $this->resolveLayout($source, $context);
    }

    /**
     * Resolve layout inheritance.
     *
     * If the source starts with the extends directive, extract all
     * {{ section 'name' }}...{{ endsection }} blocks from the child,
     * load the layout file, and replace {{ yield 'name' }} placeholders
     * with the section contents.
     */
    private function resolveLayout(string $source, CompilerContext $context): string
    {
        if (!preg_match('/^\s*\{\{\s*extends\s+\'([^\']+)\'\s*\}\}/s', $source, $extendsMatch)) {
            return $source;
        }

        $layoutName = $extendsMatch[1];
        $childSource = substr($source, strlen($extendsMatch[0]));
        $sections = $this->extractSections($childSource);

        $layoutPath = $this->resolveLayoutPath($layoutName, $context);
        $context->trackDependency($layoutPath);
        $layoutSource = $context->readFile($layoutPath);

        // Resolve includes in the layout via a fresh IncludeDirective pass.
        $includeDirective = new IncludeDirective();
        $layoutContext = $context->withTemplateDirectory(dirname($layoutPath));
        $layoutSource = $includeDirective->process($layoutSource, $layoutContext);

        // Collect yield names from the layout.
        preg_match_all(
            '/\{\{\s*yield\s+\'([^\']+)\'\s*\}\}/s',
            $layoutSource,
            $yieldMatches,
        );
        $yieldNames = $yieldMatches[1];

        // Warn about sections defined in the child that don't match
        // any yield in the layout — almost always a typo.
        $unusedSections = array_diff(
            array_keys($sections),
            $yieldNames,
        );
        if ($unusedSections !== []) {
            $available = $yieldNames !== []
                ? 'Available yields: ' . implode(', ', $yieldNames)
                : 'The layout has no yield directives';

            throw new \RuntimeException(sprintf(
                'Template defines section(s) not found in layout: %s. %s',
                implode(', ', $unusedSections),
                $available,
            ));
        }

        return $context->replaceCallback(
            '/\{\{\s*yield\s+\'([^\']+)\'\s*\}\}/s',
            function (array $matches) use ($sections): string {
                return $sections[$matches[1]] ?? '';
            },
            $layoutSource,
        );
    }

    /**
     * Resolve fragment mode: extract only the 'content' section, skip layout.
     */
    private function resolveFragment(string $source, CompilerContext $context): string
    {
        if (!preg_match('/^\s*\{\{\s*extends\s+\'([^\']+)\'\s*\}\}/s', $source, $extendsMatch)) {
            return $source;
        }

        $childSource = substr($source, strlen($extendsMatch[0]));
        $sections = $this->extractSections($childSource);

        return $sections['content'] ?? '';
    }

    /**
     * Extract {{ section 'name' }}...{{ endsection }} blocks.
     *
     * @return array<string, string>
     */
    private function extractSections(string $source): array
    {
        $sections = [];

        preg_match_all(
            '/\{\{\s*section\s+\'([^\']+)\'\s*\}\}(.*?)\{\{\s*endsection\s*\}\}/s',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        foreach ($matches as $match) {
            $sections[$match[1]] = trim($match[2]);
        }

        return $sections;
    }

    /**
     * Resolve a layout file path.
     *
     * Resolution order:
     * 1. Relative to the child template's directory
     * 2. Relative to the configured templates directory (if set)
     */
    private function resolveLayoutPath(string $name, CompilerContext $context): string
    {
        $resolved = $context->findFile($name, $context->templateDirectory);
        if ($resolved !== null) {
            return $resolved;
        }

        if ($context->templatesDirectory !== '') {
            $resolved = $context->findFile($name, $context->templatesDirectory);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $searched = $context->templateDirectory;
        if ($context->templatesDirectory !== '') {
            $searched .= ', ' . $context->templatesDirectory;
        }

        throw new \RuntimeException(sprintf(
            'Layout file not found: %s (searched: %s)',
            $name,
            $searched,
        ));
    }
}
