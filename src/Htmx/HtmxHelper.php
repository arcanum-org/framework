<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

/**
 * Template helper for htmx integration.
 *
 * Usage in templates:
 *   {{! Htmx::script() !}}   — full <script> tag for htmx
 *   {{! Htmx::csrf() !}}     — <script src="/_htmx/csrf.js"> tag
 */
final class HtmxHelper
{
    public function __construct(
        private readonly string $version,
        private readonly string $cdnUrl,
        private readonly string $integrity,
    ) {
    }

    /**
     * Render the htmx <script> tag with integrity and crossorigin attributes.
     */
    public function script(): string
    {
        $url = str_replace('{version}', $this->version, $this->cdnUrl);

        $tag = '<script src="' . $url . '"';

        if ($this->integrity !== '') {
            $tag .= ' integrity="' . $this->integrity . '" crossorigin="anonymous"';
        }

        return $tag . '></script>';
    }

    /**
     * Render the CSRF JS shim <script> tag.
     */
    public function csrf(): string
    {
        return '<script src="/_htmx/csrf.js"></script>';
    }
}
