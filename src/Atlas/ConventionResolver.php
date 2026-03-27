<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

use Arcanum\Toolkit\Strings;

final class ConventionResolver
{
    private const COMMAND_PREFIXES = [
        'POST' => 'Post',
        'PATCH' => 'Patch',
        'DELETE' => 'Delete',
        'PUT' => '',
    ];

    /**
     * @param string $rootNamespace The application's root namespace (e.g., 'App').
     */
    public function __construct(
        private readonly string $rootNamespace = 'App',
    ) {
    }

    /**
     * Resolve path segments and an HTTP method into a Route.
     *
     * @param string $path The URL path (e.g., '/catalog/products/featured').
     * @param string $method The HTTP method (e.g., 'GET', 'PUT', 'POST').
     * @param string $format The response format (e.g., 'json', 'html').
     */
    public function resolve(
        string $path,
        string $method = 'GET',
        string $format = 'json',
    ): Route {
        $method = strtoupper($method);
        $segments = $this->parseSegments($path);

        if ($segments === []) {
            throw new UnresolvableRoute('Cannot resolve an empty path via conventions.');
        }

        $className = $this->buildClassName($segments, $method);
        $handlerPrefix = $this->handlerPrefix($method);

        return new Route(
            dtoClass: $className,
            handlerPrefix: $handlerPrefix,
            format: $format,
        );
    }

    /**
     * Parse a URL path into cleaned, non-empty segments.
     *
     * @return list<string>
     */
    private function parseSegments(string $path): array
    {
        $path = trim($path, '/');

        if ($path === '') {
            return [];
        }

        return array_values(array_filter(
            explode('/', $path),
            static fn(string $segment): bool => $segment !== '',
        ));
    }

    /**
     * Build the fully-qualified DTO class name from path segments and HTTP method.
     *
     * @param list<string> $segments
     */
    private function buildClassName(array $segments, string $method): string
    {
        $pascalSegments = array_map(
            static fn(string $segment): string => Strings::pascal($segment),
            $segments,
        );

        $className = array_pop($pascalSegments);
        $typeNamespace = $this->typeNamespace($method);

        $parts = [$this->rootNamespace];

        if ($pascalSegments !== []) {
            $parts[] = $pascalSegments[0];
        }

        $parts[] = $typeNamespace;

        if (count($pascalSegments) > 1) {
            $parts = array_merge($parts, array_slice($pascalSegments, 1));
        }

        $parts[] = $className;

        return implode('\\', $parts);
    }

    /**
     * Determine the type namespace based on HTTP method.
     */
    private function typeNamespace(string $method): string
    {
        return $method === 'GET' ? 'Query' : 'Command';
    }

    /**
     * Determine the handler prefix based on HTTP method.
     */
    private function handlerPrefix(string $method): string
    {
        return self::COMMAND_PREFIXES[$method] ?? '';
    }
}
