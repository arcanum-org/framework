<?php

declare(strict_types=1);

namespace Arcanum\Toolkit;

use voku\helper\ASCII;

/**
 * Strings
 * -------
 *
 * A collection of methods for working with strings.
 */
final class Strings
{
    /**
     * Convert a string to ASCII.
     *
     * @param ASCII::*_LANGUAGE_CODE $language
     */
    public static function ascii(string $string, string $language = 'en'): string
    {
        return ASCII::to_ascii($string, $language);
    }

    /**
     * Convert a string to camel case.
     */
    public static function camel(string $string): string
    {
        return lcfirst(static::pascal($string));
    }

    /**
     * Convert a string to kebab case.
     */
    public static function kebab(string $string): string
    {
        return static::linked($string, '-');
    }

    /**
     * Convert a string to all lower case characters, where whitespace is
     * replaced with the given delimiter.
     */
    public static function linked(string $string, string $delimiter): string
    {
        if (ctype_lower($string)) {
            return $string;
        }

        $string = preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, static::pascal($string));

        return strtolower((string)$string);
    }

    /**
     * Convert a string to pascal case.
     */
    public static function pascal(string $string): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $string)));
    }

    /**
     * Convert a string to snake case.
     */
    public static function snake(string $string): string
    {
        return static::linked($string, '_');
    }

    /**
     * Convert a string to title case.
     */
    public static function title(string $string): string
    {
        return mb_convert_case($string, \MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Truncate a string to the given length, appending a suffix.
     *
     * Returns the original string if it fits within the limit.
     * Uses multibyte-safe functions.
     *
     *   Strings::truncate('Hello, world!', 10) → 'Hello, ...'
     */
    public static function truncate(string $text, int $length, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
    }

    /**
     * Convert a string to lower case (multibyte-safe).
     */
    public static function lower(string $string): string
    {
        return mb_strtolower($string, 'UTF-8');
    }

    /**
     * Convert a string to upper case (multibyte-safe).
     */
    public static function upper(string $string): string
    {
        return mb_strtoupper($string, 'UTF-8');
    }

    /**
     * Get the namespace portion of a fully qualified class name.
     *
     * Returns empty string if the class has no namespace.
     *
     *   Strings::classNamespace('App\Domain\Shop\PlaceOrder') → 'App\Domain\Shop'
     *   Strings::classNamespace('PlaceOrder') → ''
     */
    public static function classNamespace(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? '' : substr($class, 0, $pos);
    }

    /**
     * Get the short class name without the namespace.
     *
     *   Strings::classBaseName('App\Domain\Shop\PlaceOrder') → 'PlaceOrder'
     *   Strings::classBaseName('PlaceOrder') → 'PlaceOrder'
     */
    public static function classBaseName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return $pos === false ? $class : substr($class, $pos + 1);
    }

    /**
     * Strip a namespace prefix from a fully qualified class name.
     *
     * Returns the relative portion after the prefix. Throws if the
     * class is not under the given prefix.
     *
     *   Strings::stripNamespacePrefix('App\Domain\Shop\Command\PlaceOrder', 'App\Domain')
     *   → 'Shop\Command\PlaceOrder'
     */
    public static function stripNamespacePrefix(string $class, string $prefix): string
    {
        $prefix = rtrim($prefix, '\\') . '\\';

        if (!str_starts_with($class, $prefix)) {
            throw new \RuntimeException(sprintf(
                "Class '%s' is not under namespace prefix '%s'.",
                $class,
                rtrim($prefix, '\\'),
            ));
        }

        return substr($class, strlen($prefix));
    }

    /**
     * Convert a PHP namespace to a PSR-4 directory path.
     *
     * Lowercases the first segment to match the conventional directory
     * layout (App\Domain → app/Domain, not App/Domain).
     *
     *   Strings::namespacePath('App\Domain') → 'app/Domain'
     *   Strings::namespacePath('App\Domain\Shop') → 'app/Domain/Shop'
     *   Strings::namespacePath('Vendor') → 'vendor'
     */
    public static function namespacePath(string $namespace): string
    {
        $path = str_replace('\\', DIRECTORY_SEPARATOR, $namespace);

        $firstSep = strpos($path, DIRECTORY_SEPARATOR);
        if ($firstSep !== false) {
            return lcfirst(substr($path, 0, $firstSep)) . substr($path, $firstSep);
        }

        return lcfirst($path);
    }

    /**
     * Find the closest match to a needle in a list of candidates.
     *
     * Uses Levenshtein distance with a threshold of 50% of the needle
     * length. Returns null if no candidate is close enough.
     *
     * Useful for "did you mean?" suggestions in error messages.
     *
     *   Strings::closestMatch('FindAl', ['FindAll', 'Save', 'Delete'])
     *   → 'FindAll'
     *
     * @param list<string> $candidates
     */
    public static function closestMatch(
        string $needle,
        array $candidates,
    ): ?string {
        if ($candidates === []) {
            return null;
        }

        $best = null;
        $bestDistance = PHP_INT_MAX;
        $threshold = max(3, (int) ceil(strlen($needle) * 0.5));

        foreach ($candidates as $candidate) {
            $distance = levenshtein(
                strtolower($needle),
                strtolower($candidate),
            );

            if ($distance < $bestDistance && $distance <= $threshold) {
                $best = $candidate;
                $bestDistance = $distance;
            }
        }

        return $best;
    }
}
