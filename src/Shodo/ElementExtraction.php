<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

/**
 * Result of extracting an HTML element by id from template source.
 *
 * Holds both the full element (outerHTML — includes the element's own
 * tags) and just the children (innerHTML — content between the tags).
 * The renderer picks one based on the htmx swap mode.
 */
final readonly class ElementExtraction
{
    public function __construct(
        public string $outerHtml,
        public string $innerHtml,
    ) {
    }
}
