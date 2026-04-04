<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

/**
 * Resolves a DTO instance to a full URL for HTTP Location headers.
 *
 * When a command handler returns a Query DTO, this resolver builds the
 * URL that represents where the created resource can be read:
 *
 *   1. Class name → URL path via UrlResolver
 *   2. Public properties → query string params
 *   3. Base URL prepended for absolute URLs
 *
 * Returns null when the DTO class can't be resolved (not a routable DTO),
 * allowing the caller to skip the Location header gracefully.
 */
final class LocationResolver
{
    public function __construct(
        private readonly UrlResolver $urlResolver,
        private readonly string $baseUrl = '',
    ) {
    }

    /**
     * Resolve a DTO instance to a Location URL.
     *
     * @return string|null The full URL, or null if the class can't be resolved.
     */
    public function resolve(object $dto): string|null
    {
        try {
            $path = $this->urlResolver->resolve(get_class($dto));
        } catch (UnresolvableRoute | \RuntimeException) {
            return null;
        }

        $params = get_object_vars($dto);
        $url = rtrim($this->baseUrl, '/') . $path;

        if ($params !== []) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }
}
