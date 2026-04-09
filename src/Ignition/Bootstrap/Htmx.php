<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Cabinet\Application;
use Arcanum\Gather\Configuration;
use Arcanum\Htmx\EventCapture;
use Arcanum\Htmx\HtmxAuthRedirectMiddleware;
use Arcanum\Htmx\HtmxAwareResponseRenderer;
use Arcanum\Htmx\HtmxCsrfController;
use Arcanum\Htmx\HtmxEventTriggerMiddleware;
use Arcanum\Htmx\HtmxHelper;
use Arcanum\Htmx\HtmxRequestMiddleware;
use Arcanum\Hyper\HtmlResponseRenderer;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Shodo\HelperRegistry;

/**
 * Bootstraps the htmx package.
 *
 * Registers the htmx-aware response renderer (replacing the plain
 * HtmlResponseRenderer), the three middleware classes, the EventCapture
 * decorator, the CSRF controller, and the Htmx template helper.
 *
 * Reads config/htmx.php for the version pin, CDN URL, integrity hash,
 * CSRF strategy, auth-redirect mode, and Vary opt-out.
 *
 * Must run after Bootstrap\Formats (needs HtmlFormatter) and
 * Bootstrap\Helpers (needs HelperRegistry).
 */
class Htmx implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        /** @var string $version */
        $version = $config->get('htmx.version') ?? '4.0.0-beta1';

        /** @var string $cdnUrl */
        $cdnUrl = $config->get('htmx.cdn_url')
            ?? 'https://unpkg.com/htmx.org@{version}/dist/htmx.min.js';

        /** @var string $integrity */
        $integrity = $config->get('htmx.integrity') ?? '';

        /** @var bool $addVary */
        $addVary = $config->get('htmx.vary') !== false;

        /** @var string $loginUrl */
        $loginUrl = $config->get('htmx.auth_redirect') ?? '/login';

        /** @var bool $useRefresh */
        $useRefresh = $config->get('htmx.auth_refresh') === true;

        $container->service(HtmxAwareResponseRenderer::class);
        $container->service(EventCapture::class);
        $container->service(HtmxEventTriggerMiddleware::class);
        $container->service(HtmxCsrfController::class);

        // Alias so FormatRegistry resolves the htmx-aware renderer.
        $container->factory(HtmlResponseRenderer::class, function () use ($container) {
            return $container->get(HtmxAwareResponseRenderer::class);
        });

        // Scalar constructor params via specify.
        $container->specify(HtmxRequestMiddleware::class, '$addVaryHeader', $addVary);
        $container->service(HtmxRequestMiddleware::class);

        $container->specify(HtmxAuthRedirectMiddleware::class, '$loginUrl', $loginUrl);
        $container->specify(HtmxAuthRedirectMiddleware::class, '$useRefresh', $useRefresh);
        $container->service(HtmxAuthRedirectMiddleware::class);

        // Template helper: {{! Htmx::script() !}}, {{! Htmx::csrf() !}}
        if ($container->has(HelperRegistry::class)) {
            /** @var HelperRegistry $helpers */
            $helpers = $container->get(HelperRegistry::class);
            $helpers->register('Htmx', new HtmxHelper($version, $cdnUrl, $integrity));
        }
    }
}
