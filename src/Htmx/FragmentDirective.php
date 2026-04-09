<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

use Arcanum\Shodo\CompilerContext;
use Arcanum\Shodo\CompilerDirective;

/**
 * Custom directive for explicit innerHTML fragment markers.
 *
 * The auto-fragment extraction (extractElementById) returns outerHTML —
 * the full element including its tags. That pairs naturally with
 * hx-swap="outerHTML" and the positional swap modes (beforebegin,
 * afterend). But three swap modes need just the inner content without
 * the wrapper: innerHTML, afterbegin, beforeend.
 *
 * The {{ fragment 'id' }} directive is the opt-in for those cases.
 * Developers mark the inner content boundary explicitly:
 *
 *   <div id="main">
 *     {{ fragment 'main' }}
 *     <p>This content is returned without the wrapper div.</p>
 *     {{ endfragment }}
 *   </div>
 *
 * In the full-render path, process() strips the markers — they're
 * transparent to the rendered output. In the element-extraction path
 * (renderElementById), extractFragment() finds and returns the inner
 * content before the compiler runs, giving the formatter innerHTML
 * without the wrapper element.
 */
final class FragmentDirective implements CompilerDirective
{
    public function keywords(): array
    {
        return ['fragment', 'endfragment'];
    }

    public function priority(): int
    {
        return 350;
    }

    /**
     * Strip {{ fragment 'name' }} and {{ endfragment }} markers.
     *
     * In the full-render path these markers are transparent — the
     * rendered output is identical with or without them.
     */
    public function process(string $source, CompilerContext $context): string
    {
        // Strip {{ fragment 'name' }} markers.
        $source = $context->replace(
            '/\{\{\s*fragment\s+\'[^\']+\'\s*\}\}/',
            '',
            $source,
        );

        // Strip {{ endfragment }} markers.
        $source = $context->replace(
            '/\{\{\s*endfragment\s*\}\}/',
            '',
            $source,
        );

        return $source;
    }

    /**
     * Extract the content between {{ fragment 'id' }} and {{ endfragment }}.
     *
     * Searches the raw template source for a fragment matching the given
     * id and returns the inner content (no wrapper element, no markers).
     * Returns null when no matching fragment exists.
     *
     * This runs on the raw source before compilation — the markers are
     * still present. The returned content is then compiled separately.
     */
    public static function extractFragment(string $source, string $id): ?string
    {
        $escapedId = preg_quote($id, '/');
        $pattern = '/\{\{\s*fragment\s+\'' . $escapedId . '\'\s*\}\}(.*?)\{\{\s*endfragment\s*\}\}/s';

        if (!preg_match($pattern, $source, $match)) {
            return null;
        }

        return $match[1];
    }
}
