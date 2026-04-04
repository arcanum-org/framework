<?php

declare(strict_types=1);

namespace Arcanum\Atlas\Attribute;

/**
 * Restricts which response formats a DTO accepts.
 *
 * When the router resolves a request for this DTO, it checks the URL
 * extension against the allowed list. A disallowed format returns
 * 406 Not Acceptable. No attribute means all registered formats are allowed.
 *
 * ```php
 * #[AllowedFormats('json', 'html')]
 * final class Products { ... }
 * ```
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AllowedFormats
{
    /** @var list<string> */
    public readonly array $formats;

    public function __construct(string ...$formats)
    {
        $this->formats = array_values(array_map('strtolower', $formats));
    }
}
