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
use Arcanum\Htmx\FragmentDirective;
use Arcanum\Htmx\HtmxRequestMiddleware;
use Arcanum\Hyper\HtmlResponseRenderer;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Shodo\HelperRegistry;
use Arcanum\Shodo\TemplateCompiler;

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
        $container->service(HtmxCsrfController::class);

        // Alias so FormatRegistry resolves the htmx-aware renderer.
        $container->factory(HtmlResponseRenderer::class, function () use ($container) {
            return $container->get(HtmxAwareResponseRenderer::class);
        });

        // Decorate the event dispatcher with EventCapture so ClientBroadcast
        // events are recorded during handler dispatch. The decorator wraps
        // the existing dispatcher — handlers that inject EventDispatcherInterface
        // get the capture-wrapped version automatically.
        $container->decorator(
            \Psr\EventDispatcher\EventDispatcherInterface::class,
            function (object $dispatcher): EventCapture {
                return new EventCapture($dispatcher);
            },
        );

        // EventCapture resolves to the decorated dispatcher instance.
        $container->factory(EventCapture::class, function () use ($container) {
            $dispatcher = $container->get(\Psr\EventDispatcher\EventDispatcherInterface::class);
            assert($dispatcher instanceof EventCapture);
            return $dispatcher;
        });
        $container->service(HtmxEventTriggerMiddleware::class);

        // Scalar constructor params via specify.
        $container->specify(HtmxRequestMiddleware::class, '$addVaryHeader', $addVary);
        $container->service(HtmxRequestMiddleware::class);

        $container->specify(HtmxAuthRedirectMiddleware::class, '$loginUrl', $loginUrl);
        $container->specify(HtmxAuthRedirectMiddleware::class, '$useRefresh', $useRefresh);
        $container->service(HtmxAuthRedirectMiddleware::class);

        // Register the {{ fragment }} custom directive with the compiler.
        if ($container->has(TemplateCompiler::class)) {
            /** @var TemplateCompiler $compiler */
            $compiler = $container->get(TemplateCompiler::class);
            $compiler->directives()->register(new FragmentDirective());
        }

        // Template helper: {{! Htmx::script() !}}, {{! Htmx::csrf() !}}
        if ($container->has(HelperRegistry::class)) {
            /** @var HelperRegistry $helpers */
            $helpers = $container->get(HelperRegistry::class);
            $helpers->register('Htmx', new HtmxHelper($version, $cdnUrl, $integrity));
        }
    }
}
