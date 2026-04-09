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
use Arcanum\Shodo\Formatters\HtmlFormatter;
use Arcanum\Shodo\HelperRegistry;
use Psr\EventDispatcher\EventDispatcherInterface;

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

        // Register the htmx-aware renderer, replacing HtmlResponseRenderer
        // in the container for HTML format responses.
        $container->factory(HtmxAwareResponseRenderer::class, function () use ($container) {
            /** @var HtmlFormatter $formatter */
            $formatter = $container->get(HtmlFormatter::class);
            return new HtmxAwareResponseRenderer($formatter);
        });

        // Alias so FormatRegistry resolves the htmx-aware renderer
        // when the HTML format is requested.
        $container->factory(HtmlResponseRenderer::class, function () use ($container) {
            return $container->get(HtmxAwareResponseRenderer::class);
        });

        // EventCapture wraps the event dispatcher to record ClientBroadcast events.
        $container->factory(EventCapture::class, function () use ($container) {
            /** @var EventDispatcherInterface $dispatcher */
            $dispatcher = $container->get(EventDispatcherInterface::class);
            return new EventCapture($dispatcher);
        });

        // Middleware classes.
        $container->factory(HtmxRequestMiddleware::class, function () use ($container, $addVary) {
            /** @var HtmxAwareResponseRenderer $renderer */
            $renderer = $container->get(HtmxAwareResponseRenderer::class);
            return new HtmxRequestMiddleware($renderer, $addVary);
        });

        $container->factory(HtmxEventTriggerMiddleware::class, function () use ($container) {
            /** @var EventCapture $capture */
            $capture = $container->get(EventCapture::class);
            return new HtmxEventTriggerMiddleware($capture);
        });

        $container->factory(HtmxAuthRedirectMiddleware::class, function () use ($loginUrl, $useRefresh) {
            return new HtmxAuthRedirectMiddleware($loginUrl, $useRefresh);
        });

        // CSRF controller.
        $container->factory(HtmxCsrfController::class, function () {
            return new HtmxCsrfController();
        });

        // Template helper: {{ Htmx::script() }}, {{ Htmx::csrf() }}
        if ($container->has(HelperRegistry::class)) {
            /** @var HelperRegistry $helpers */
            $helpers = $container->get(HelperRegistry::class);
            $helpers->register('Htmx', new HtmxHelper($version, $cdnUrl, $integrity));
        }
    }
}
