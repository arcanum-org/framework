<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

/**
 * Stores explicit path → Route mappings that bypass convention-based resolution.
 *
 * Custom routes take priority over convention routing. Each entry maps a
 * normalized path to one or more HTTP methods with a shared DTO class.
 * Handler prefixes follow the same convention as ConventionResolver
 * (POST → 'Post', PATCH → 'Patch', DELETE → 'Delete', PUT/GET → '').
 */
final class RouteMap
{
    private const HANDLER_PREFIXES = [
        'POST' => 'Post',
        'PATCH' => 'Patch',
        'DELETE' => 'Delete',
        'PUT' => '',
        'GET' => '',
    ];

    /**
     * Registered custom routes.
     *
     * @var array<string, array{dtoClass: string, methods: list<string>, format: string, isPage: bool}>
     */
    private array $routes = [];

    /**
     * Register a custom route.
     *
     * @param string $path The URL path (e.g., '/this/is/custom').
     * @param string $dtoClass The fully-qualified DTO class name.
     * @param list<string> $methods Allowed HTTP methods (e.g., ['GET'], ['PUT', 'POST']).
     * @param string $format Default response format.
     * @param bool $isPage Whether this route is a page (template-driven, no handler).
     */
    public function register(
        string $path,
        string $dtoClass,
        array $methods = ['GET'],
        string $format = 'json',
        bool $isPage = false,
    ): void {
        $this->routes[$this->normalize($path)] = [
            'dtoClass' => $dtoClass,
            'methods' => array_map('strtoupper', $methods),
            'format' => $format,
            'isPage' => $isPage,
        ];
    }

    /**
     * Check if a path has a custom route registered.
     */
    public function has(string $path): bool
    {
        return isset($this->routes[$this->normalize($path)]);
    }

    /**
     * Resolve a custom route for the given path and method.
     *
     * @param string $path The URL path.
     * @param string $method The HTTP method.
     * @param string|null $extensionFormat Format parsed from file extension, or null to use the route default.
     *
     * @throws MethodNotAllowed If the path is registered but the method is not allowed.
     */
    public function resolve(string $path, string $method, string|null $extensionFormat = null): Route
    {
        $normalized = $this->normalize($path);
        $method = strtoupper($method);

        if (!isset($this->routes[$normalized])) {
            throw new UnresolvableRoute(sprintf(
                'No custom route registered for path "%s".',
                $path,
            ));
        }

        $entry = $this->routes[$normalized];

        if (!in_array($method, $entry['methods'], true)) {
            throw new MethodNotAllowed($entry['methods']);
        }

        return new Route(
            dtoClass: $entry['dtoClass'],
            handlerPrefix: self::HANDLER_PREFIXES[$method] ?? '',
            format: $extensionFormat ?? $entry['format'],
            isPage: $entry['isPage'],
        );
    }

    /**
     * Get the allowed HTTP methods for a path.
     *
     * @return list<string> Empty array if the path is not registered.
     */
    public function allowedMethods(string $path): array
    {
        $normalized = $this->normalize($path);

        if (!isset($this->routes[$normalized])) {
            return [];
        }

        return $this->routes[$normalized]['methods'];
    }

    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }
}
