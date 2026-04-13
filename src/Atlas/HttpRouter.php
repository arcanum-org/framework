<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

use Arcanum\Atlas\Attribute\AllowedFormats;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;

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
        private readonly ?LoggerInterface $logger = null,
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
        if (class_exists($queryRoute->dtoClass) || class_exists($queryRoute->dtoClass . 'Handler')) {
            $methods = array_merge($methods, self::QUERY_METHODS);
        }

        $commandRoute = $this->resolver->resolve(path: $cleanPath, method: 'PUT');
        if (class_exists($commandRoute->dtoClass) || class_exists($commandRoute->dtoClass . 'Handler')) {
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
            $route = $this->routeMap->resolve(
                $cleanPath,
                $method,
                $hasExtension ? $extensionFormat : null,
            );
            $this->assertFormatAllowed($route);

            $this->logger?->debug('Route resolved', [
                'type' => 'custom',
                'dto' => $route->dtoClass,
                'format' => $route->format,
            ]);

            return $route;
        }

        if ($this->pages !== null && $this->pages->has($cleanPath)) {
            if ($method !== 'GET') {
                $this->logger?->notice('Method not allowed', [
                    'path' => $cleanPath,
                    'method' => $method,
                    'allowed' => self::QUERY_METHODS,
                ]);
                throw new MethodNotAllowed(self::QUERY_METHODS);
            }

            $route = $this->pages->resolve(
                $cleanPath,
                $hasExtension ? $extensionFormat : null,
            );
            $this->assertFormatAllowed($route);

            $this->logger?->debug('Route resolved', [
                'type' => 'page',
                'dto' => $route->dtoClass,
                'format' => $route->format,
            ]);

            return $route;
        }

        $route = $this->resolver->resolve(
            path: $cleanPath,
            method: $method,
            format: $extensionFormat,
        );

        if (class_exists($route->dtoClass)) {
            $this->assertFormatAllowed($route);

            $this->logger?->debug('Route resolved', [
                'type' => 'convention',
                'dto' => $route->dtoClass,
                'format' => $route->format,
            ]);

            return $route;
        }

        // If the DTO class doesn't exist but the handler does, allow it —
        // the kernel will create a dynamic Command or Query DTO.
        if (class_exists($route->dtoClass . 'Handler')) {
            $this->logger?->debug('Route resolved', [
                'type' => 'convention',
                'dto' => $route->dtoClass,
                'format' => $route->format,
            ]);

            return $route;
        }

        $this->throwForMissingClass($route, $cleanPath, $method, $extensionFormat);
    }

    /**
     * Throw 406 Not Acceptable if the DTO restricts formats and the
     * requested format isn't in the allowed list.
     */
    private function assertFormatAllowed(Route $route): void
    {
        if (!class_exists($route->dtoClass)) {
            return;
        }

        $ref = new \ReflectionClass($route->dtoClass);
        $attrs = $ref->getAttributes(AllowedFormats::class);

        if ($attrs === []) {
            return;
        }

        /** @var AllowedFormats $allowed */
        $allowed = $attrs[0]->newInstance();

        if (!in_array($route->format, $allowed->formats, true)) {
            $this->logger?->notice('Format not acceptable', [
                'format' => $route->format,
                'allowed' => $allowed->formats,
            ]);
            throw new HttpException(
                StatusCode::NotAcceptable,
                sprintf(
                    'Format "%s" is not allowed for "%s". Allowed formats: %s.',
                    $route->format,
                    $route->dtoClass,
                    implode(', ', $allowed->formats),
                ),
            );
        }
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

        if (class_exists($alternateRoute->dtoClass) || class_exists($alternateRoute->dtoClass . 'Handler')) {
            $this->logger?->notice('Method not allowed', [
                'path' => $path,
                'method' => $method,
                'allowed' => $alternateMethods,
            ]);
            throw new MethodNotAllowed($alternateMethods);
        }

        $this->logger?->debug('Route not found', ['path' => $path]);

        throw (new HttpException(
            StatusCode::NotFound,
            sprintf('No route found for path "%s".', $path),
        ))->withSuggestion(
            "Check your URL, or run 'php arcanum validate:handlers'"
                . " to verify route registration",
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
