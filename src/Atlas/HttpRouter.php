<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use Psr\Http\Message\ServerRequestInterface;

final class HttpRouter implements Router
{
    private const QUERY_METHODS = ['GET'];
    private const COMMAND_METHODS = ['PUT', 'POST', 'PATCH', 'DELETE'];

    /**
     * @param ConventionResolver $resolver The convention-based path-to-namespace resolver.
     * @param RouteMap|null $routeMap Custom route overrides, checked before convention routing.
     * @param PageResolver|null $pages The page resolver, or null if no pages are registered.
     * @param string $defaultFormat Fallback format when no file extension is present.
     */
    public function __construct(
        private readonly ConventionResolver $resolver,
        private readonly RouteMap|null $routeMap = null,
        private readonly PageResolver|null $pages = null,
        private readonly string $defaultFormat = 'json',
    ) {
    }

    /**
     * Determine which HTTP methods are allowed for a given path.
     *
     * Returns an empty array if the path doesn't resolve to any route
     * (neither pages, nor Query, nor Command namespace).
     *
     * @return list<string>
     */
    public function allowedMethods(string $path): array
    {
        [$cleanPath] = $this->parseExtension($path);

        // Custom routes take priority.
        if ($this->routeMap !== null) {
            $customMethods = $this->routeMap->allowedMethods($cleanPath);
            if ($customMethods !== []) {
                return $customMethods;
            }
        }

        // Pages only allow GET.
        if ($this->pages !== null && $this->pages->has($cleanPath)) {
            return self::QUERY_METHODS;
        }

        $methods = [];

        $queryRoute = $this->resolver->resolve(path: $cleanPath, method: 'GET');
        if (class_exists($queryRoute->dtoClass)) {
            $methods = array_merge($methods, self::QUERY_METHODS);
        }

        $commandRoute = $this->resolver->resolve(path: $cleanPath, method: 'PUT');
        if (class_exists($commandRoute->dtoClass)) {
            $methods = array_merge($methods, self::COMMAND_METHODS);
        }

        return $methods;
    }

    public function resolve(object $input): Route
    {
        if (!$input instanceof ServerRequestInterface) {
            throw new UnresolvableRoute(sprintf(
                'HttpRouter expects a ServerRequestInterface, got %s.',
                get_class($input),
            ));
        }

        $path = $input->getUri()->getPath();
        $method = strtoupper($input->getMethod());

        [$cleanPath, $extensionFormat, $hasExtension] = $this->parseExtension($path);

        // Custom routes take priority over everything.
        if ($this->routeMap !== null && $this->routeMap->has($cleanPath)) {
            return $this->routeMap->resolve(
                $cleanPath,
                $method,
                $hasExtension ? $extensionFormat : null,
            );
        }

        if ($this->pages !== null && $this->pages->has($cleanPath)) {
            if ($method !== 'GET') {
                throw new MethodNotAllowed(self::QUERY_METHODS);
            }

            return $this->pages->resolve(
                $cleanPath,
                $hasExtension ? $extensionFormat : null,
            );
        }

        $route = $this->resolver->resolve(
            path: $cleanPath,
            method: $method,
            format: $extensionFormat,
        );

        if (class_exists($route->dtoClass)) {
            return $route;
        }

        $this->throwForMissingClass($route, $cleanPath, $method, $extensionFormat);
    }

    /**
     * When the resolved DTO class doesn't exist, determine whether this is a
     * 405 (right path, wrong method) or 404 (nothing at this path).
     *
     * @return never
     */
    private function throwForMissingClass(
        Route $route,
        string $path,
        string $method,
        string $format,
    ): void {
        $isQuery = $method === 'GET';
        $alternateMethods = $isQuery ? self::COMMAND_METHODS : self::QUERY_METHODS;
        $alternateMethod = $isQuery ? 'PUT' : 'GET';

        $alternateRoute = $this->resolver->resolve(
            path: $path,
            method: $alternateMethod,
            format: $format,
        );

        if (class_exists($alternateRoute->dtoClass)) {
            throw new MethodNotAllowed($alternateMethods);
        }

        throw new HttpException(
            StatusCode::NotFound,
            sprintf('No route found for path "%s".', $path),
        );
    }

    /**
     * Strip a file extension from the path and return the cleaned path,
     * the parsed format, and whether an extension was found.
     *
     * @return array{string, string, bool} [cleanPath, format, hasExtension]
     */
    private function parseExtension(string $path): array
    {
        $path = rtrim($path, '/');

        if ($path === '' || $path === '/') {
            return [$path, $this->defaultFormat, false];
        }

        $lastSlash = strrpos($path, '/');
        $lastSegment = $lastSlash !== false ? substr($path, $lastSlash + 1) : $path;

        $dotPos = strrpos($lastSegment, '.');
        if ($dotPos === false || $dotPos === 0) {
            return [$path, $this->defaultFormat, false];
        }

        $extension = substr($lastSegment, $dotPos + 1);

        if ($extension === '') {
            return [$path, $this->defaultFormat, false];
        }

        $cleanLastSegment = substr($lastSegment, 0, $dotPos);
        $cleanPath = $lastSlash !== false
            ? substr($path, 0, $lastSlash + 1) . $cleanLastSegment
            : $cleanLastSegment;

        return [$cleanPath, strtolower($extension), true];
    }
}
