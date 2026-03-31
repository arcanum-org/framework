<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

/**
 * Runtime lookup for per-route middleware.
 *
 * Populated by MiddlewareDiscovery at bootstrap time, then queried
 * by RouteDispatcher at request time.
 */
final class MiddlewareRegistry
{
    /**
     * @var array<string, RouteMiddleware>
     */
    private array $middleware = [];

    /**
     * Register middleware for a DTO class.
     */
    public function register(string $dtoClass, RouteMiddleware $middleware): void
    {
        $this->middleware[$dtoClass] = $middleware;
    }

    /**
     * Get middleware for a DTO class.
     *
     * Returns an empty RouteMiddleware if no middleware is registered.
     */
    public function for(string $dtoClass): RouteMiddleware
    {
        return $this->middleware[$dtoClass] ?? new RouteMiddleware();
    }
}
