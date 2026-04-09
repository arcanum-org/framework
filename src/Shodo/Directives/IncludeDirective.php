<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Directives;

use Arcanum\Shodo\CompilerContext;
use Arcanum\Shodo\CompilerDirective;

/**
 * Resolves {{ include 'path' }} directives by inlining file contents.
 *
 * Paths are relative to the template's directory, with the shared
 * templates directory as a fallback. Supports nesting (included files
 * may themselves contain include directives). Guards against circular
 * includes with a depth limit of 10.
 */
final class IncludeDirective implements CompilerDirective
{
    public function keywords(): array
    {
        return ['include'];
    }

    public function priority(): int
    {
        return 100;
    }

    public function process(string $source, CompilerContext $context): string
    {
        if ($context->templateDirectory === '') {
            return $source;
        }

        return $this->resolveIncludes($source, $context->templateDirectory, $context);
    }

    private function resolveIncludes(
        string $source,
        string $baseDirectory,
        CompilerContext $context,
        int $depth = 0,
    ): string {
        if ($depth > 10) {
            throw new \RuntimeException(
                'Include depth limit exceeded (max 10)'
                    . ' — check for circular includes',
            );
        }

        return $context->replaceCallback(
            '/\{\{\s*include\s+\'([^\']+)\'\s*\}\}/',
            function (array $matches) use ($baseDirectory, $context, $depth): string {
                $path = $this->resolveIncludePath(
                    $matches[1],
                    $baseDirectory,
                    $context,
                );
                $context->trackDependency($path);
                $contents = $context->readFile($path);

                return $this->resolveIncludes(
                    $contents,
                    dirname($path),
                    $context,
                    $depth + 1,
                );
            },
            $source,
        );
    }

    /**
     * Resolve an include path.
     *
     * Resolution order:
     * 1. Relative to the current template's directory
     * 2. Relative to the configured templates directory (if set)
     */
    private function resolveIncludePath(
        string $path,
        string $baseDirectory,
        CompilerContext $context,
    ): string {
        $resolved = $context->findFile($path, $baseDirectory);
        if ($resolved !== null) {
            return $resolved;
        }

        if ($context->templatesDirectory !== '') {
            $resolved = $context->findFile($path, $context->templatesDirectory);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        $searched = $baseDirectory;
        if ($context->templatesDirectory !== '') {
            $searched .= ', ' . $context->templatesDirectory;
        }

        throw new \RuntimeException(sprintf(
            'Include file not found: %s (searched: %s)',
            $path,
            $searched,
        ));
    }
}
