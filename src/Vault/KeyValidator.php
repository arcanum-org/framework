<?php

declare(strict_types=1);

namespace Arcanum\Vault;

/**
 * Validates cache keys against PSR-16 constraints.
 *
 * Keys must be non-empty strings that do not contain the reserved
 * characters `{}()/\@:`.
 */
final class KeyValidator
{
    private const RESERVED = '/[{}()\/\\\\@:]/';

    public static function validate(string $key): void
    {
        if ($key === '') {
            throw new InvalidArgument('Cache key must not be empty.');
        }

        if (preg_match(self::RESERVED, $key)) {
            throw new InvalidArgument(
                sprintf('Cache key "%s" contains reserved characters {}()/\\@:.', $key),
            );
        }
    }

    /**
     * Validate an iterable of keys.
     *
     * @param iterable<string> $keys
     */
    public static function validateMultiple(iterable $keys): void
    {
        foreach ($keys as $key) {
            self::validate($key);
        }
    }
}
