<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Helpers;

/**
 * Template helper for HTML utilities and context-specific output encoding.
 *
 * Shodo's {{ }} applies htmlspecialchars() to all output — correct for HTML
 * body text and quoted attributes, but insufficient for URL, JavaScript,
 * CSS, and unquoted-attribute contexts. These methods provide the right
 * encoding for each context per OWASP XSS Prevention guidelines.
 *
 * Usage in templates:
 *   href="{{ Html::url($link) }}"
 *   <script>var name = '{{ Html::js($name) }}';</script>
 *   <div title={{ Html::attr($title) }}>
 *   style="color: {{ Html::css($color) }}"
 *   {{ Html::nonce() }}
 *   class="{{ Html::classIf($active, 'selected') }}"
 */
final class HtmlHelper
{
    /**
     * Validate a URL for safe use in href/src attributes.
     *
     * Allows http, https, mailto, tel, and relative paths. Rejects
     * javascript:, data:, and all other schemes. Returns the URL
     * unchanged for {{ }} to HTML-encode, or empty string if unsafe.
     */
    public function url(string $href): string
    {
        $href = trim($href);

        if ($href === '') {
            return '';
        }

        // Relative URLs (paths, fragments, query strings) are safe.
        if ($href[0] === '/' || $href[0] === '.' || $href[0] === '#' || $href[0] === '?') {
            return $href;
        }

        // Strip null bytes and control characters that could bypass scheme detection.
        $normalized = preg_replace('/[\x00-\x1f\x7f]/', '', $href) ?? $href;

        // Check for a scheme — if present, it must be in the allowlist.
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $normalized, $matches)) {
            $scheme = strtolower($matches[1]);
            return match ($scheme) {
                'http', 'https', 'mailto', 'tel' => $href,
                default => '',
            };
        }

        // No scheme detected — treat as relative path.
        return $href;
    }

    /**
     * Encode a value for safe use in a JavaScript string context.
     *
     * Whitelist approach: alphanumeric characters and , . _ are left
     * as-is. Everything else is encoded as \uHHHH (or surrogate pairs
     * for characters outside the BMP).
     *
     * The recommended pattern is data- attributes instead of inline JS,
     * but this method exists for cases where inline JS is unavoidable.
     */
    public function js(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $result = preg_replace_callback(
            '/[^a-zA-Z0-9,._]/u',
            static function (array $match): string {
                $char = $match[0];
                $codepoint = mb_ord($char, 'UTF-8');

                // BMP characters: \uHHHH
                if ($codepoint <= 0xFFFF) {
                    return sprintf('\\u%04X', $codepoint);
                }

                // Characters outside BMP: surrogate pair
                $codepoint -= 0x10000;
                $high = 0xD800 | ($codepoint >> 10);
                $low = 0xDC00 | ($codepoint & 0x3FF);
                return sprintf('\\u%04X\\u%04X', $high, $low);
            },
            $value,
        );

        return $result ?? '';
    }

    /**
     * Encode a value for safe use in an HTML attribute context.
     *
     * Stricter than htmlspecialchars — encodes all non-alphanumeric
     * characters as &#xHH; hex entities. Safe for unquoted attributes
     * and event handler attributes (onclick, etc.) per OWASP guidelines.
     */
    public function attr(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $result = preg_replace_callback(
            '/[^a-zA-Z0-9,.\-_]/u',
            static function (array $match): string {
                $char = $match[0];
                $codepoint = mb_ord($char, 'UTF-8');

                // Replace undefined HTML characters with the replacement character.
                if (
                    ($codepoint <= 0x1F && $codepoint !== 0x09 && $codepoint !== 0x0A && $codepoint !== 0x0D)
                    || ($codepoint >= 0x7F && $codepoint <= 0x9F)
                ) {
                    return '&#xFFFD;';
                }

                return sprintf('&#x%02X;', $codepoint);
            },
            $value,
        );

        return $result ?? '';
    }

    /**
     * Encode a value for safe use in a CSS context.
     *
     * Encodes all non-alphanumeric characters as \HH (hex codepoint
     * followed by a space). For the rare case of user data in style
     * attributes — prefer avoiding this pattern entirely.
     */
    public function css(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $result = preg_replace_callback(
            '/[^a-zA-Z0-9]/u',
            static function (array $match): string {
                $codepoint = mb_ord($match[0], 'UTF-8');

                return sprintf('\\%X ', $codepoint);
            },
            $value,
        );

        return $result ?? '';
    }

    /**
     * Generate a random CSP nonce.
     */
    public function nonce(): string
    {
        return base64_encode(random_bytes(16));
    }

    /**
     * Return a CSS class name if the condition is true, empty string otherwise.
     */
    public function classIf(bool $condition, string $class): string
    {
        return $condition ? $class : '';
    }
}
