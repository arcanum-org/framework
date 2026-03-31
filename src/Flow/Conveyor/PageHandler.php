<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor;

/**
 * Framework-provided handler for all pages.
 *
 * Extracts the template data from the Page DTO and returns it
 * as an array for the renderer. This is intentionally trivial —
 * pages are presentation-only, so the handler's only job is to
 * bridge the Conveyor pipeline to the rendering layer.
 */
final class PageHandler
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(Page $page): array
    {
        return $page->toArray();
    }
}
