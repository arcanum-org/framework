<?php

declare(strict_types=1);

namespace Arcanum\Htmx;

use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use Arcanum\Flow\River\LazyResource;
use Arcanum\Flow\River\Stream;
use Psr\Http\Message\ResponseInterface;

/**
 * Serves the htmx CSRF JS shim at /_htmx/csrf.js.
 *
 * The shim listens to htmx:configRequest and auto-injects the CSRF
 * token from the <meta name="csrf-token"> tag into every non-GET
 * request. Handles boosted-navigation token rotation by reading the
 * updated meta tag after each full page swap.
 *
 * Returns a cacheable JS response (Content-Type: text/javascript,
 * Cache-Control: public, max-age=86400).
 */
final class HtmxCsrfController
{
    /**
     * The JS source is inlined rather than loaded from a file — it's
     * small enough that the overhead of a file read isn't worth it,
     * and it avoids a Parchment dependency.
     */
    private const JS = <<<'JS'
        document.addEventListener('htmx:configRequest', function(event) {
            var method = (event.detail.verb || event.detail.method || '').toUpperCase();
            if (method !== 'GET' && method !== 'HEAD' && method !== 'OPTIONS') {
                var meta = document.querySelector('meta[name="csrf-token"]');
                event.detail.headers['X-CSRF-TOKEN'] = meta ? meta.getAttribute('content') : '';
            }
        });
        JS;

    public function handle(): ResponseInterface
    {
        $body = new Stream(LazyResource::for('php://memory', 'w+'));
        $body->write(self::JS);

        return new Response(
            new Message(
                new Headers([
                    'Content-Type' => ['text/javascript; charset=UTF-8'],
                    'Content-Length' => [(string) strlen(self::JS)],
                    'Cache-Control' => ['public, max-age=86400'],
                ]),
                $body,
                Version::v11,
            ),
            StatusCode::OK,
        );
    }
}
