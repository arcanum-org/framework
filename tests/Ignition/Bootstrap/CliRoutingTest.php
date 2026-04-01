<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition\Bootstrap;

use Arcanum\Atlas\CliRouteMap;
use Arcanum\Atlas\CliRouter;
use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\Route;
use Arcanum\Atlas\Router;
use Arcanum\Cabinet\Container;
use Arcanum\Codex\Hydrator;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrap\CliRouting;
use Arcanum\Rune\CliExceptionWriter;
use Arcanum\Rune\ConsoleOutput;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CliRouting::class)]
#[UsesClass(CliRouteMap::class)]
#[UsesClass(CliRouter::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
#[UsesClass(Container::class)]
#[UsesClass(\Arcanum\Cabinet\SimpleProvider::class)]
#[UsesClass(\Arcanum\Cabinet\PrototypeProvider::class)]
#[UsesClass(\Arcanum\Codex\Resolver::class)]
#[UsesClass(\Arcanum\Codex\Event\ClassRequested::class)]
#[UsesClass(ConventionResolver::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(CliExceptionWriter::class)]
#[UsesClass(Hydrator::class)]
#[UsesClass(Input::class)]
#[UsesClass(Route::class)]
#[UsesClass(Strings::class)]
final class CliRoutingTest extends TestCase
{
    /**
     * @param array<string, mixed> $appConfig
     * @param array<string, mixed> $routesConfig
     */
    private function buildContainer(array $appConfig, array $routesConfig = []): Container
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);
        $container->instance(\Psr\Container\ContainerInterface::class, $container);

        $config = new Configuration([
            'app' => $appConfig,
            'routes' => $routesConfig,
        ]);
        $container->instance(Configuration::class, $config);

        return $container;
    }

    // ---------------------------------------------------------------
    // Router registration
    // ---------------------------------------------------------------

    public function testRegistersCliRouterAsRouterInterface(): void
    {
        // Arrange
        $container = $this->buildContainer(['namespace' => 'Arcanum\\Test\\Fixture']);

        // Act
        (new CliRouting())->bootstrap($container);

        // Assert
        $router = $container->get(Router::class);
        $this->assertInstanceOf(CliRouter::class, $router);
    }

    public function testRouterResolvesConventionRoutes(): void
    {
        // Arrange
        $container = $this->buildContainer(['namespace' => 'Arcanum\\Test\\Fixture']);
        (new CliRouting())->bootstrap($container);

        /** @var Router $router */
        $router = $container->get(Router::class);

        // Act
        $route = $router->resolve(new Input('query:shop:products'));

        // Assert
        $this->assertSame('Arcanum\\Test\\Fixture\\Shop\\Query\\Products', $route->dtoClass);
    }

    public function testRegistersCustomCliRoutes(): void
    {
        // Arrange
        $container = $this->buildContainer(
            ['namespace' => 'Arcanum\\Test\\Fixture'],
            ['cli' => [
                'stripe:webhook' => ['class' => 'App\\Stripe\\Webhook', 'type' => 'command'],
            ]],
        );
        (new CliRouting())->bootstrap($container);

        /** @var CliRouteMap $routeMap */
        $routeMap = $container->get(CliRouteMap::class);

        // Assert
        $this->assertTrue($routeMap->has('stripe:webhook'));
    }

    public function testCustomCliRouteDefaultsToCommandType(): void
    {
        // Arrange
        $container = $this->buildContainer(
            ['namespace' => 'Arcanum\\Test\\Fixture'],
            ['cli' => [
                'deploy' => ['class' => 'App\\Deploy'],
            ]],
        );
        (new CliRouting())->bootstrap($container);

        /** @var CliRouteMap $routeMap */
        $routeMap = $container->get(CliRouteMap::class);

        // Assert
        $this->assertTrue($routeMap->has('deploy'));
    }

    // ---------------------------------------------------------------
    // ConventionResolver sharing
    // ---------------------------------------------------------------

    public function testCreatesConventionResolverWhenNotRegistered(): void
    {
        // Arrange
        $container = $this->buildContainer(['namespace' => 'Arcanum\\Test\\Fixture']);

        // Act
        (new CliRouting())->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(ConventionResolver::class));
    }

    public function testReusesExistingConventionResolver(): void
    {
        // Arrange
        $container = $this->buildContainer(['namespace' => 'Arcanum\\Test\\Fixture']);
        $existing = new ConventionResolver(rootNamespace: 'Arcanum\\Test\\Fixture');
        $container->instance(ConventionResolver::class, $existing);

        // Act
        (new CliRouting())->bootstrap($container);

        // Assert — same instance reused
        $this->assertSame($existing, $container->get(ConventionResolver::class));
    }

    // ---------------------------------------------------------------
    // Output registration
    // ---------------------------------------------------------------

    public function testRegistersConsoleOutputAsDefault(): void
    {
        // Arrange
        $container = $this->buildContainer(['namespace' => 'App']);

        // Act
        (new CliRouting())->bootstrap($container);

        // Assert
        $output = $container->get(Output::class);
        $this->assertInstanceOf(ConsoleOutput::class, $output);
    }

    public function testDoesNotOverrideExistingOutput(): void
    {
        // Arrange
        $container = $this->buildContainer(['namespace' => 'App']);
        $custom = $this->createStub(Output::class);
        $container->instance(Output::class, $custom);

        // Act
        (new CliRouting())->bootstrap($container);

        // Assert
        $this->assertSame($custom, $container->get(Output::class));
    }

    // ---------------------------------------------------------------
    // CliExceptionWriter registration
    // ---------------------------------------------------------------

    public function testRegistersCliExceptionWriter(): void
    {
        // Arrange
        $container = $this->buildContainer(['namespace' => 'App']);

        // Act
        (new CliRouting())->bootstrap($container);

        // Assert
        $writer = $container->get(CliExceptionWriter::class);
        $this->assertInstanceOf(CliExceptionWriter::class, $writer);
    }

    // ---------------------------------------------------------------
    // Hydrator registration
    // ---------------------------------------------------------------

    public function testRegistersHydrator(): void
    {
        // Arrange
        $container = $this->buildContainer(['namespace' => 'App']);

        // Act
        (new CliRouting())->bootstrap($container);

        // Assert
        $this->assertInstanceOf(Hydrator::class, $container->get(Hydrator::class));
    }

    public function testDoesNotOverrideExistingHydrator(): void
    {
        // Arrange
        $container = $this->buildContainer(['namespace' => 'App']);
        $existing = new Hydrator();
        $container->instance(Hydrator::class, $existing);

        // Act
        (new CliRouting())->bootstrap($container);

        // Assert
        $this->assertSame($existing, $container->get(Hydrator::class));
    }

    // ---------------------------------------------------------------
    // Missing config
    // ---------------------------------------------------------------

    public function testThrowsForMissingNamespace(): void
    {
        // Arrange
        $container = $this->buildContainer([]);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing required config "app.namespace"');
        (new CliRouting())->bootstrap($container);
    }

    public function testThrowsForEmptyNamespace(): void
    {
        // Arrange
        $container = $this->buildContainer(['namespace' => '']);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing required config "app.namespace"');
        (new CliRouting())->bootstrap($container);
    }
}
