<?php

declare(strict_types=1);

namespace Arcanum\Glitch;

interface ArcanumException
{
    /**
     * Stable, human-readable error category.
     *
     * Forward-compatible with RFC 9457 Problem Details `title` field.
     */
    public function getTitle(): string;

    /**
     * Optional fix hint shown when verbose_errors is enabled.
     */
    public function getSuggestion(): ?string;
}
