<?php

declare(strict_types=1);

namespace Arcanum\Flow\Conveyor;

/**
 * A dynamic Page DTO for template-driven pages.
 *
 * Pages are presentation-only routes: handler never, DTO optional,
 * template required. The Page DTO wraps template data and routes
 * all pages to PageHandler via a fixed handlerBaseName.
 *
 * ```php
 * // Pure static page (no DTO, no query params):
 * $dto = new Page('App\Pages\About', []);
 *
 * // Page with query params:
 * $dto = new Page('App\Pages\About', ['theme' => 'dark']);
 *
 * // Page with dedicated DTO data:
 * $dto = new Page('App\Pages\About', ['title' => 'About Us']);
 * ```
 */
final class Page extends DynamicDTO
{
    /**
     * @param string $dtoClass The virtual DTO class name (for template discovery).
     * @param array<string, mixed> $data Template data.
     */
    public function __construct(
        private readonly string $dtoClass,
        array $data = [],
    ) {
        parent::__construct($this->dtoClass, $data);
    }

    /**
     * Always routes to PageHandler, regardless of the virtual class name.
     */
    public function handlerBaseName(): string
    {
        return self::class;
    }

    /**
     * The virtual DTO class name, used by the renderer to discover
     * the co-located template file.
     */
    public function dtoClass(): string
    {
        return $this->dtoClass;
    }
}
