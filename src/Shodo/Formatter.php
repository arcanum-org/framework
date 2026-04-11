<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Pure formatting contract — converts data into a string.
 *
 * Formatters are transport-agnostic. They produce content (JSON, CSV,
 * HTML, plain text, key-value pairs, tables) without any knowledge of
 * HTTP responses, CLI output, or any other delivery mechanism.
 *
 * Each Kernel wraps the formatted string into its transport's response:
 * HTTP kernels build a ResponseInterface, CLI kernels write to Output.
 */
interface Formatter
{
    /**
     * Format the given data into a string.
     *
     * @param string $templatePath Pre-resolved template path. Template-based
     *                             formatters render this template; non-template
     *                             formatters ignore it. Empty string means
     *                             no template (use fallback).
     * @param string $dtoClass The DTO class name, used by template-based
     *                         formatters for helper scoping.
     */
    public function format(mixed $data, string $templatePath = '', string $dtoClass = ''): string;
}
