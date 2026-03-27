<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

use Psr\Http\Message\ServerRequestInterface;

final class HttpRouter implements Router
{
    /**
     * @param ConventionResolver $resolver The convention-based path-to-namespace resolver.
     * @param PageResolver|null $pages The page resolver, or null if no pages are registered.
     * @param string $defaultFormat Fallback format when no file extension is present.
     */
    public function __construct(
        private readonly ConventionResolver $resolver,
        private readonly PageResolver|null $pages = null,
        private readonly string $defaultFormat = 'json',
    ) {
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
        $method = $input->getMethod();

        [$cleanPath, $extensionFormat, $hasExtension] = $this->parseExtension($path);

        if ($this->pages !== null && $this->pages->has($cleanPath)) {
            return $this->pages->resolve(
                $cleanPath,
                $hasExtension ? $extensionFormat : null,
            );
        }

        return $this->resolver->resolve(
            path: $cleanPath,
            method: $method,
            format: $extensionFormat,
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
