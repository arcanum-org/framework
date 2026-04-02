<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Cabinet\Application;
use Arcanum\Gather\Configuration;
use Arcanum\Hyper\Middleware\Options;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\HyperKernel;
use Arcanum\Ignition\Kernel;
use Arcanum\Session\CsrfMiddleware;
use Arcanum\Session\SessionMiddleware;

/**
 * Registers global HTTP middleware from config/middleware.php.
 *
 * Reads from config/middleware.php:
 *   - global (optional) — ordered list of middleware class names
 *
 * After app middleware, the framework appends its own middleware:
 *   - Options — handles OPTIONS requests with 204 + Allow header
 */
class Middleware implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        $kernel = $container->get(Kernel::class);

        if (!$kernel instanceof HyperKernel) {
            return;
        }

        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        /** @var list<class-string<\Psr\Http\Server\MiddlewareInterface>>|null $middleware */
        $middleware = $config->get('middleware.global');

        // Session middleware — outermost framework layer, wraps everything.
        $kernel->middleware(SessionMiddleware::class);
        $kernel->middleware(CsrfMiddleware::class);

        // App middleware from config.
        foreach ($middleware ?? [] as $class) {
            $kernel->middleware($class);
        }

        // Framework middleware — always present, innermost layer.
        $kernel->middleware(Options::class);
    }
}
