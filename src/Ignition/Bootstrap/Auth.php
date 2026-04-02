<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Auth\ActiveIdentity;
use Arcanum\Auth\AuthMiddleware;
use Arcanum\Auth\CompositeGuard;
use Arcanum\Auth\Guard;
use Arcanum\Auth\Identity;
use Arcanum\Auth\SessionGuard;
use Arcanum\Auth\TokenGuard;
use Arcanum\Cabinet\Application;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\HyperKernel;
use Arcanum\Ignition\Kernel;
use Arcanum\Session\ActiveSession;

/**
 * Registers authentication infrastructure in the container.
 *
 * Reads `config/auth.php` for guard and resolver configuration.
 * Registers ActiveIdentity, Guard, and AuthMiddleware (HTTP only).
 *
 * Must run after Bootstrap\Sessions (SessionGuard needs ActiveSession).
 */
class Auth implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        $activeIdentity = new ActiveIdentity();
        $container->instance(ActiveIdentity::class, $activeIdentity);

        // Also register as the Identity interface for container injection.
        // Handlers that typehint Identity get the resolved identity (or error).
        $container->factory(
            Identity::class,
            fn() => $activeIdentity->get(),
        );

        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        $kernel = $container->get(Kernel::class);

        if ($kernel instanceof HyperKernel) {
            $this->registerHttpGuard($container, $config);
        }
    }

    private function registerHttpGuard(Application $container, Configuration $config): void
    {
        $guardName = $this->string($config, 'auth.guard', 'session');

        $container->factory(Guard::class, function () use ($container, $config, $guardName): Guard {
            return match ($guardName) {
                'session' => $this->buildSessionGuard($container, $config),
                'token' => $this->buildTokenGuard($config),
                'composite' => new CompositeGuard(
                    $this->buildSessionGuard($container, $config),
                    $this->buildTokenGuard($config),
                ),
                default => throw new \RuntimeException(
                    sprintf('Unknown auth guard "%s".', $guardName),
                ),
            };
        });

        $container->factory(
            AuthMiddleware::class,
            function () use ($container): AuthMiddleware {
                /** @var Guard $guard */
                $guard = $container->get(Guard::class);
                /** @var ActiveIdentity $activeIdentity */
                $activeIdentity = $container->get(ActiveIdentity::class);
                return new AuthMiddleware($guard, $activeIdentity);
            },
        );
    }

    private function buildSessionGuard(Application $container, Configuration $config): SessionGuard
    {
        /** @var ActiveSession $session */
        $session = $container->get(ActiveSession::class);

        $resolver = $config->get('auth.resolvers.identity');
        $resolverFn = $resolver instanceof \Closure
            ? $resolver
            : fn(string $id) => null;

        return new SessionGuard($session, $resolverFn);
    }

    private function buildTokenGuard(Configuration $config): TokenGuard
    {
        $resolver = $config->get('auth.resolvers.token');
        $resolverFn = $resolver instanceof \Closure
            ? $resolver
            : fn(string $token) => null;

        return new TokenGuard($resolverFn);
    }

    private function string(Configuration $config, string $key, string $default): string
    {
        $value = $config->get($key);
        return is_string($value) ? $value : $default;
    }
}
