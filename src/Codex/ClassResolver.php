<?php

declare(strict_types=1);

namespace Arcanum\Codex;

use Psr\Container\ContainerInterface;

interface ClassResolver
{
    /**
     * Resolve a class.
     *
     * @template T of object
     * @param class-string<T>|(callable(ContainerInterface): T) $className
     * @return T
     * @throws Error\UnknownClass
     * @throws Error\UnresolvableClass
     * @throws Error\UnresolvablePrimitive
     * @throws Error\UnresolvableUnionType
     */
    public function resolve(string|callable $className, bool $isDependency = false): object;
}
