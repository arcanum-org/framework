<?php

declare(strict_types=1);

namespace Arcanum\Codex;

final class PrimitiveResolver
{
    /**
     * Resolve a primitive type.
     */
    public static function resolve(\ReflectionParameter $parameter): mixed
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        if ($parameter->isVariadic()) {
            return [];
        }

        $type = $parameter->getType();
        if ($type !== null && $type instanceof \ReflectionUnionType) {
            $typeNames = array_map(
                static fn(\ReflectionType $t): string =>
                    ($t instanceof \ReflectionNamedType) ? $t->getName() : (string) $t,
                $type->getTypes(),
            );
            throw new Error\UnresolvableUnionType(implode(",", $typeNames));
        }

        throw new Error\UnresolvablePrimitive(message: $parameter->getName());
    }
}
