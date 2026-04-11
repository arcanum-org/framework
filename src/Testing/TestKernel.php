<?php

declare(strict_types=1);

namespace Arcanum\Testing;

use Arcanum\Auth\ActiveIdentity;
use Arcanum\Auth\Identity;
use Arcanum\Cabinet\Application;
use Arcanum\Cabinet\Container;
use Arcanum\Hourglass\Clock;
use Arcanum\Hourglass\FrozenClock;
use Arcanum\Ignition\Kernel;
use Arcanum\Testing\Internal\TestHyperKernel;
use Arcanum\Testing\Internal\TestRuneKernel;
use Arcanum\Vault\ArrayDriver;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;

/**
 * Test harness kernel for app developers.
 *
 * Builds a shared Cabinet container up front with the bindings every test
 * needs: a FrozenClock for deterministic time, an in-memory ArrayDriver
 * cache, and a request-scoped ActiveIdentity. Subsequent commits in the
 * testing-utilities arc will lazily compose real HyperKernel and RuneKernel
 * instances against this same container so HTTP and CLI dispatch share state.
 */
final class TestKernel implements Kernel
{
    private readonly Application $container;
    private readonly Clock $clock;
    private readonly CacheInterface $cache;
    private readonly ActiveIdentity $identity;
    private readonly string $rootDirectory;

    private HttpTestSurface|null $http = null;
    private CliTestSurface|null $cli = null;

    public function __construct(
        Clock|null $clock = null,
        CacheInterface|null $cache = null,
        string|null $rootDirectory = null,
    ) {
        $this->clock = $clock ?? new FrozenClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $this->cache = $cache ?? new ArrayDriver($this->clock);
        $this->identity = new ActiveIdentity();
        $this->rootDirectory = rtrim($rootDirectory ?? '/app', DIRECTORY_SEPARATOR);

        $container = new Container();
        $container->instance(Clock::class, $this->clock);
        $container->instance(CacheInterface::class, $this->cache);
        $container->instance(ActiveIdentity::class, $this->identity);
        $container->instance(Kernel::class, $this);

        $this->container = $container;
    }

    public function container(): Application
    {
        return $this->container;
    }

    public function clock(): Clock
    {
        return $this->clock;
    }

    public function cache(): CacheInterface
    {
        return $this->cache;
    }

    public function rootDirectory(): string
    {
        return $this->rootDirectory;
    }

    public function configDirectory(): string
    {
        return $this->rootDirectory . DIRECTORY_SEPARATOR . 'config';
    }

    public function filesDirectory(): string
    {
        return $this->rootDirectory . DIRECTORY_SEPARATOR . 'files';
    }

    /**
     * @return list<string>
     */
    public function requiredEnvironmentVariables(): array
    {
        return [];
    }

    /**
     * No-op. TestKernel owns its own pre-built container; the wrapped
     * HyperKernel and RuneKernel have their own bootstrap loops that run
     * on first dispatch through `http()` / `cli()`. This method exists
     * solely so TestKernel satisfies the `Kernel` interface and can be
     * passed to helpers/services that take a `Kernel` parameter just to
     * read `rootDirectory()`.
     */
    public function bootstrap(Application $container): void
    {
    }

    /**
     * No-op. The wrapped surfaces' kernels expose their own
     * `terminate()` via `HttpTestSurface::terminate()`.
     */
    public function terminate(): void
    {
    }

    /**
     * Bind an Identity for the current test.
     *
     * Named after the widely-used `actingAs` convention across PHP test
     * harnesses — reads naturally in tests:
     * `$kernel->actingAs($alice)->http()...`.
     */
    public function actingAs(Identity $identity): self
    {
        $this->identity->set($identity);

        return $this;
    }

    /**
     * Lazily build and memoize the HTTP test surface.
     *
     * Most tests touch one transport; constructing the HyperKernel only on
     * first call means a CLI-only test never pays for HTTP wiring it doesn't
     * use. Cross-transport tests still see consistent state because the
     * surface bootstraps against the shared container.
     */
    public function http(): HttpTestSurface
    {
        if ($this->http === null) {
            $this->http = new HttpTestSurface(
                new TestHyperKernel($this->rootDirectory),
                $this->container,
            );
        }

        return $this->http;
    }

    /**
     * Lazily build and memoize the CLI test surface.
     *
     * Mirror of `http()`. Cross-transport tests see consistent state
     * because both surfaces bootstrap against the same container.
     */
    public function cli(): CliTestSurface
    {
        if ($this->cli === null) {
            $this->cli = new CliTestSurface(
                new TestRuneKernel($this->rootDirectory),
                $this->container,
            );
        }

        return $this->cli;
    }
}
