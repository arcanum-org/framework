<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Directives;

use Arcanum\Shodo\CompilerContext;
use Arcanum\Shodo\CompilerDirective;

/**
 * Compiles {{ csrf }} into a raw helper call that emits the CSRF hidden input.
 *
 * Runs before the expression catch-all so the bare keyword is not
 * mistaken for a generic expression.
 */
final class CsrfDirective implements CompilerDirective
{
    public function keywords(): array
    {
        return ['csrf'];
    }

    public function priority(): int
    {
        return 400;
    }

    public function process(string $source, CompilerContext $context): string
    {
        return $context->replace(
            '/\{\{\s*csrf\s*\}\}/',
            '<?= $__helpers[\'Csrf\']->field() ?>',
            $source,
        );
    }
}
