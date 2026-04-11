<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Helpers;

use Arcanum\Atlas\UrlResolver;

/**
 * Template helper for URL generation.
 *
 * Usage in templates:
 *   {{ Route::url('App\\Domain\\Query\\Health') }}
 *   {{ Route::asset('css/app.css') }}
 */
final class RouteHelper
{
    public function __construct(
        private readonly UrlResolver $resolver,
        private readonly string $baseUrl = '',
    ) {
    }

    public function url(string $dtoClass): string
    {
        return rtrim($this->baseUrl, '/') . $this->resolver->resolve($dtoClass);
    }

    public function asset(string $path): string
    {
        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }
}
