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

        // Auto-wired: Codex resolves HtmlFormatter from the container.
        $container->service(HtmxAwareResponseRenderer::class);

        // Alias so FormatRegistry resolves the htmx-aware renderer
        // when the HTML format is requested.
        $container->factory(HtmlResponseRenderer::class, function () use ($container) {
            return $container->get(HtmxAwareResponseRenderer::class);
        });

        // Auto-wired: Codex resolves EventDispatcherInterface from the container.
        $container->service(EventCapture::class);

        // Auto-wired: Codex resolves EventCapture from the container.
        $container->service(HtmxEventTriggerMiddleware::class);

        // Auto-wired: Codex resolves HtmxAwareResponseRenderer. The bool
        // $addVary scalar can't be auto-wired — needs a factory.
        // TODO: Use $container->specify() once it's on the Application interface.
        $container->factory(HtmxRequestMiddleware::class, function () use ($container, $addVary) {
            /** @var HtmxAwareResponseRenderer $renderer */
            $renderer = $container->get(HtmxAwareResponseRenderer::class);
            return new HtmxRequestMiddleware($renderer, $addVary);
        });

        // Scalar constructor params ($loginUrl, $useRefresh) can't be auto-wired.
        // TODO: Use $container->specify() once it's on the Application interface.
        $container->factory(HtmxAuthRedirectMiddleware::class, function () use ($loginUrl, $useRefresh) {
            return new HtmxAuthRedirectMiddleware($loginUrl, $useRefresh);
        });

        // Auto-wired: no constructor params.
        $container->service(HtmxCsrfController::class);

        // Template helper: {{! Htmx::script() !}}, {{! Htmx::csrf() !}}
        if ($container->has(HelperRegistry::class)) {
            /** @var HelperRegistry $helpers */
            $helpers = $container->get(HelperRegistry::class);
            $helpers->register('Htmx', new HtmxHelper($version, $cdnUrl, $integrity));
        }
    }
}
