<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Cabinet\Application;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\HyperKernel;
use Arcanum\Ignition\Kernel;
use Arcanum\Session\ActiveSession;
use Arcanum\Session\CacheSessionDriver;
use Arcanum\Session\CookieSessionDriver;
use Arcanum\Session\SessionConfig;
use Arcanum\Session\SessionDriver;
use Arcanum\Toolkit\Encryption\Encryptor;
use Arcanum\Vault\CacheManager;
use Arcanum\Vault\FileDriver;

/**
 * Registers session infrastructure in the container.
 *
 * Reads `config/session.php` for driver and cookie configuration.
 * Registers SessionDriver, SessionConfig, and ActiveSession.
 *
 * Session middleware is registered in Bootstrap\Middleware — this
 * bootstrapper only prepares the services.
 *
 * HTTP-only — skipped entirely for CLI kernels.
 * Must run after Bootstrap\Security and Bootstrap\Cache.
 */
class Sessions implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        $kernel = $container->get(Kernel::class);

        if (!$kernel instanceof HyperKernel) {
            return;
        }

        /** @var Configuration $config */
        $config = $container->get(Configuration::class);

        $sessionConfig = new SessionConfig(
            cookieName: $this->string($config, 'session.cookie', 'arcanum_session'),
            lifetime: $this->int($config, 'session.lifetime', 7200),
            path: $this->string($config, 'session.path', '/'),
            domain: $this->string($config, 'session.domain', ''),
            secure: $this->bool($config, 'session.secure', true),
            httpOnly: $this->bool($config, 'session.http_only', true),
            sameSite: $this->string($config, 'session.same_site', 'Lax'),
        );

        $container->instance(SessionConfig::class, $sessionConfig);

        $registry = new ActiveSession();
        $container->instance(ActiveSession::class, $registry);

        $driverName = $this->string($config, 'session.driver', 'file');
        $this->registerDriver($container, $driverName, $kernel);
    }

    private function registerDriver(Application $container, string $driver, HyperKernel $kernel): void
    {
        match ($driver) {
            'file' => $container->factory(
                SessionDriver::class,
                fn() => new CacheSessionDriver(
                    new FileDriver(
                        $kernel->filesDirectory() . DIRECTORY_SEPARATOR . 'sessions',
                    ),
                ),
            ),
            'cache' => $container->factory(
                SessionDriver::class,
                function () use ($container): CacheSessionDriver {
                    /** @var CacheManager $manager */
                    $manager = $container->get(CacheManager::class);
                    return new CacheSessionDriver(
                        $manager->store($this->resolveStoreName($container)),
                    );
                },
            ),
            'cookie' => $container->factory(
                SessionDriver::class,
                function () use ($container): CookieSessionDriver {
                    /** @var Encryptor $encryptor */
                    $encryptor = $container->get(Encryptor::class);
                    return new CookieSessionDriver($encryptor);
                },
            ),
            default => throw new \RuntimeException(
                sprintf('Unknown session driver "%s".', $driver),
            ),
        };
    }

    private function resolveStoreName(Application $container): string
    {
        /** @var Configuration $config */
        $config = $container->get(Configuration::class);
        $store = $config->get('session.store');
        return is_string($store) ? $store : '';
    }

    private function string(Configuration $config, string $key, string $default): string
    {
        $value = $config->get($key);
        return is_string($value) ? $value : $default;
    }

    private function int(Configuration $config, string $key, int $default): int
    {
        $value = $config->get($key);
        return is_int($value) ? $value : $default;
    }

    private function bool(Configuration $config, string $key, bool $default): bool
    {
        $value = $config->get($key);
        return is_bool($value) ? $value : $default;
    }
}
