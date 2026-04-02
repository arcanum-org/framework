<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition\Bootstrap;

use Arcanum\Cabinet\Container;
use Arcanum\Gather\Configuration;
use Arcanum\Hyper\Middleware\Options;
use Arcanum\Ignition\Bootstrap\Middleware;
use Arcanum\Ignition\HyperKernel;
use Arcanum\Ignition\Kernel;
use Arcanum\Session\CsrfMiddleware;
use Arcanum\Session\SessionMiddleware;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Middleware::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
#[UsesClass(Container::class)]
#[UsesClass(\Arcanum\Cabinet\SimpleProvider::class)]
#[UsesClass(\Arcanum\Codex\Resolver::class)]
#[UsesClass(\Arcanum\Codex\Event\ClassRequested::class)]
#[UsesClass(HyperKernel::class)]
final class MiddlewareTest extends TestCase
{
    // -----------------------------------------------------------
    // Registers middleware from config
    // -----------------------------------------------------------

    public function testRegistersMiddlewareFromConfig(): void
    {
        // Arrange
        $kernel = $this->createMock(HyperKernel::class);
        $kernel->expects($this->exactly(5))
            ->method('middleware')
            ->willReturnCallback(fn(string $class) => match (true) {
                in_array($class, [
                    SessionMiddleware::class,
                    CsrfMiddleware::class,
                    'App\Http\Middleware\Cors',
                    'App\Http\Middleware\Auth',
                    Options::class,
                ]) => null,
                default => $this->fail("Unexpected middleware class: $class"),
            });

        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);
        $container->instance(Kernel::class, $kernel);
        $container->instance(Configuration::class, new Configuration([
            'middleware' => [
                'global' => [
                    'App\Http\Middleware\Cors',
                    'App\Http\Middleware\Auth',
                ],
            ],
        ]));

        $bootstrapper = new Middleware();

        // Act
        $bootstrapper->bootstrap($container);
    }

    // -----------------------------------------------------------
    // No config key — graceful no-op
    // -----------------------------------------------------------

    public function testNoConfigKeyRegistersOnlyFrameworkMiddleware(): void
    {
        // Arrange
        $kernel = $this->createMock(HyperKernel::class);
        $kernel->expects($this->exactly(3))
            ->method('middleware')
            ->willReturnCallback(fn(string $class) => match (true) {
                in_array($class, [
                    SessionMiddleware::class,
                    CsrfMiddleware::class,
                    Options::class,
                ]) => null,
                default => $this->fail("Unexpected middleware class: $class"),
            });

        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);
        $container->instance(Kernel::class, $kernel);
        $container->instance(Configuration::class, new Configuration([]));

        $bootstrapper = new Middleware();

        // Act
        $bootstrapper->bootstrap($container);
    }

    // -----------------------------------------------------------
    // Empty array — no calls
    // -----------------------------------------------------------

    public function testEmptyArrayRegistersOnlyFrameworkMiddleware(): void
    {
        // Arrange
        $kernel = $this->createMock(HyperKernel::class);
        $kernel->expects($this->exactly(3))
            ->method('middleware')
            ->willReturnCallback(fn(string $class) => match (true) {
                in_array($class, [
                    SessionMiddleware::class,
                    CsrfMiddleware::class,
                    Options::class,
                ]) => null,
                default => $this->fail("Unexpected middleware class: $class"),
            });

        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);
        $container->instance(Kernel::class, $kernel);
        $container->instance(Configuration::class, new Configuration([
            'middleware' => ['global' => []],
        ]));

        $bootstrapper = new Middleware();

        // Act
        $bootstrapper->bootstrap($container);
    }

    // -----------------------------------------------------------
    // Non-HyperKernel — returns early
    // -----------------------------------------------------------

    public function testNonHyperKernelReturnsEarly(): void
    {
        // Arrange — a Kernel that is not a HyperKernel
        $kernel = $this->createStub(Kernel::class);

        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);
        $container->instance(Kernel::class, $kernel);
        // No Configuration registered — would blow up if the bootstrapper
        // didn't return early, proving the guard works.

        $bootstrapper = new Middleware();

        // Act — should not throw
        $bootstrapper->bootstrap($container);

        // Assert
        $this->addToAssertionCount(1);
    }
}
