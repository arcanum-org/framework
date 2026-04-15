<?php

declare(strict_types=1);

namespace Arcanum\Atlas;

use Arcanum\Toolkit\Strings;

/**
 * Converts a DTO class name back into a URL path.
 *
 * Reverses the convention used by ConventionResolver: strips the root
 * namespace, removes the Query/Command type namespace, and converts
 * PascalCase segments to kebab-case.
 *
 * Custom routes registered in a RouteMap are checked first via reverse
 * lookup. Pages namespace classes are handled separately.
 */
final class UrlResolver
{
    public function __construct(
        private readonly string $rootNamespace,
        private readonly ?RouteMap $routeMap = null,
        private readonly ?string $pagesNamespace = null,
    ) {
    }

    /**
     * Resolve a DTO class name to a URL path.
     *
     * @throws UnresolvableRoute If the class is not under the root or pages namespace.
     */
    public function resolve(string $dtoClass): string
    {
        if ($this->routeMap !== null) {
            $custom = $this->routeMap->reverseLookup($dtoClass);
            if ($custom !== null) {
                return $custom;
            }
        }

        if ($this->pagesNamespace !== null && str_starts_with($dtoClass, $this->pagesNamespace . '\\')) {
            return $this->resolvePages($dtoClass);
        }

        return $this->resolveConvention($dtoClass);
    }

    private function resolveConvention(string $dtoClass): string
    {
        $relative = Strings::stripNamespacePrefix($dtoClass, $this->rootNamespace);
        $segments = explode('\\', $relative);

        // Root-level DTO: Query\Health → ['Query', 'Health']
        // Domain DTO: Shop\Query\Products → ['Shop', 'Query', 'Products']
        // Deep DTO: Shop\Query\Electronics\Products → ['Shop', 'Query', 'Electronics', 'Products']

        $typeIndex = $this->findTypeIndex($segments);

        // The segment immediately before the type is the domain name.
        // If the last segment after the type matches this domain, the forward
        // resolver produced it from a single-segment path — collapse the duplicate.
        // e.g., TaskLists\Query\TaskLists → ['TaskLists'] not ['TaskLists', 'TaskLists']
        // Also handles deeper nesting: Shop\Products\Query\Products → ['Shop', 'Products']
        $domainSegment = $typeIndex > 0 ? $segments[$typeIndex - 1] : null;

        // Remove the type namespace segment (Query/Command)
        array_splice($segments, $typeIndex, 1);

        // Collapse trailing duplicate of the domain segment.
        if ($domainSegment !== null && count($segments) >= 2 && end($segments) === $domainSegment) {
            array_pop($segments);
        }

        return '/' . implode('/', array_map(
            static fn(string $segment): string => Strings::kebab($segment),
            $segments,
        ));
    }

    private function resolvePages(string $dtoClass): string
    {
        $relative = Strings::stripNamespacePrefix($dtoClass, (string) $this->pagesNamespace);
        $segments = explode('\\', $relative);

        return '/' . implode('/', array_map(
            static fn(string $segment): string => Strings::kebab($segment),
            $segments,
        ));
    }

    /**
     * Find the index of the Query or Command type namespace segment.
     *
     * @param list<string> $segments
     */
    private function findTypeIndex(array $segments): int
    {
        foreach ($segments as $i => $segment) {
            if ($segment === 'Query' || $segment === 'Command') {
                return $i;
            }
        }

        throw new UnresolvableRoute(sprintf(
            'Cannot reverse-resolve "%s": no Query or Command namespace found.',
            implode('\\', $segments),
        ));
    }
}
