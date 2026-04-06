<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Glitch\ArcanumException;

/**
 * Thrown when a requested format is not registered.
 *
 * Used by CliFormatRegistry for CLI format resolution errors.
 * For HTTP, Hyper's FormatRegistry throws HttpException(406) directly.
 */
class UnsupportedFormat extends \RuntimeException implements ArcanumException
{
    public function __construct(string $extension)
    {
        parent::__construct(
            sprintf('Format "%s" is not supported.', $extension),
        );
    }

    public function getTitle(): string
    {
        return 'Unsupported Format';
    }

    public function getSuggestion(): ?string
    {
        return null;
    }
}
