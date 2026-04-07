<?php

declare(strict_types=1);

namespace Arcanum\Shodo;

use Arcanum\Shodo\Attribute\WithHelper;
use Psr\Container\ContainerInterface;

/**
 * Resolves template helpers for a given DTO class.
 *
 * Three layers, ordered from least to most specific:
 *
 *   1. Global helpers from {@see HelperRegistry} (registered in
 *      `app/Helpers/Helpers.php`).
 *   2. Domain-scoped helpers discovered by {@see HelperDiscovery} from
 *      co-located `Helpers.php` files; deeper namespace prefixes win.
 *   3. Per-DTO helpers declared via the {@see WithHelper} class attribute.
 *
 * Each layer overrides the aliases from the layers below it. The DTO's own
 * attribute-declared helpers always have the final word.
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

        if ($this->container !== null) {
            $helpers = $this->mergeAttributes($helpers, $dtoClass);
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

    /**
     * Merge per-DTO helpers declared via the {@see WithHelper} attribute.
     *
     * Reads class-level attributes via reflection, resolves each declared
     * helper class from the container, and merges them onto the helper set
     * with the highest precedence.
     *
     * @param array<string, object> $helpers
     * @return array<string, object>
     */
    private function mergeAttributes(array $helpers, string $dtoClass): array
    {
        assert($this->container !== null);

        if (!class_exists($dtoClass)) {
            return $helpers;
        }

        $reflection = new \ReflectionClass($dtoClass);
        $attributes = $reflection->getAttributes(WithHelper::class);

        foreach ($attributes as $attribute) {
            $withHelper = $attribute->newInstance();
            /** @var object $helper */
            $helper = $this->container->get($withHelper->className);
            $helpers[$withHelper->alias] = $helper;
        }

        return $helpers;
    }
}
