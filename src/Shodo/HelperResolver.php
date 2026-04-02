<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Psr\Container\ContainerInterface;

/**
 * Resolves template helpers for a given DTO class.
 *
 * Merges global (framework-provided) helpers with domain-scoped helpers
 * discovered from co-located Helpers.php files. Domain aliases override
 * global ones, so a Shop domain can replace a global helper with a
 * domain-specific implementation.
 */
final class HelperResolver
{
    /**
     * @var array<string, array<string, object>>
     */
    private array $cache = [];

    public function __construct(
        private readonly HelperRegistry $global,
        private readonly ?HelperDiscovery $discovery = null,
        private readonly ?ContainerInterface $container = null,
    ) {
    }

    /**
     * Return the helper instances available for a given DTO class.
     *
     * @return array<string, object> alias => helper instance
     */
    public function for(string $dtoClass): array
    {
        if (isset($this->cache[$dtoClass])) {
            return $this->cache[$dtoClass];
        }

        $helpers = $this->global->all();

        if ($this->discovery !== null && $this->container !== null) {
            $discovered = $this->discovery->discover();
            $helpers = $this->mergeDiscovered($helpers, $discovered, $dtoClass);
        }

        $this->cache[$dtoClass] = $helpers;

        return $helpers;
    }

    /**
     * Merge discovered domain-scoped helpers onto the global set.
     *
     * Walks namespace prefixes from shortest (shallowest) to longest (deepest).
     * Each matching prefix's helpers override earlier ones, so deeper domains
     * take precedence.
     *
     * @param array<string, object> $helpers
     * @param array<string, array<string, string>> $discovered prefix => alias => class
     * @return array<string, object>
     */
    private function mergeDiscovered(array $helpers, array $discovered, string $dtoClass): array
    {
        assert($this->container !== null);

        $prefixes = array_keys($discovered);
        usort($prefixes, fn(string $a, string $b) => strlen($a) <=> strlen($b));

        foreach ($prefixes as $prefix) {
            if (!str_starts_with($dtoClass, $prefix . '\\')) {
                continue;
            }

            foreach ($discovered[$prefix] as $alias => $className) {
                /** @var object $helper */
                $helper = $this->container->get($className);
                $helpers[$alias] = $helper;
            }
        }

        return $helpers;
    }
}
