<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Helper;

/**
 * Maps template helper aliases to helper class instances.
 *
 * Each alias (e.g. "Route", "Format", "Html") maps to a helper object
 * whose public methods are callable from templates as {{ Alias::method() }}.
 */
final class HelperRegistry
{
    /**
     * @var array<string, object>
     */
    private array $helpers = [];

    /**
     * Register a helper instance under an alias.
     */
    public function register(string $alias, object $helper): void
    {
        $this->helpers[$alias] = $helper;
    }

    /**
     * Retrieve a helper by alias.
     *
     * @throws UnknownHelper If the alias is not registered.
     */
    public function get(string $alias): object
    {
        if (!isset($this->helpers[$alias])) {
            throw new UnknownHelper($alias, array_keys($this->helpers));
        }

        return $this->helpers[$alias];
    }

    /**
     * Check if an alias is registered.
     */
    public function has(string $alias): bool
    {
        return isset($this->helpers[$alias]);
    }

    /**
     * Return all registered helpers.
     *
     * @return array<string, object>
     */
    public function all(): array
    {
        return $this->helpers;
    }
}
