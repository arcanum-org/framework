<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition\Bootstrap;

use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\HttpRouter;
use Arcanum\Atlas\PageDiscovery;
use Arcanum\Atlas\PageResolver;
use Arcanum\Atlas\Route;
use Arcanum\Atlas\RouteMap;
use Arcanum\Atlas\Router;
use Arcanum\Cabinet\Container;
use Arcanum\Codex\Hydrator;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrap\Formats;
use Arcanum\Ignition\Bootstrap\Routing;
use Arcanum\Ignition\HyperKernel;
use Arcanum\Ignition\Kernel;
use Arcanum\Hyper\FormatRegistry;
use Arcanum\Hyper\JsonResponseRenderer;
use Arcanum\Shodo\Format;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Routing::class)]
#[CoversClass(Formats::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
#[UsesClass(Container::class)]
#[UsesClass(\Arcanum\Cabinet\SimpleProvider::class)]
#[UsesClass(\Arcanum\Cabinet\PrototypeProvider::class)]
#[UsesClass(\Arcanum\Codex\Resolver::class)]
#[UsesClass(\Arcanum\Codex\Event\ClassRequested::class)]
#[UsesClass(ConventionResolver::class)]
#[UsesClass(HttpRouter::class)]
#[UsesClass(PageResolver::class)]
#[UsesClass(Route::class)]
#[UsesClass(Hydrator::class)]
#[UsesClass(Format::class)]
#[UsesClass(FormatRegistry::class)]
#[UsesClass(JsonResponseRenderer::class)]
#[UsesClass(RouteMap::class)]
#[UsesClass(PageDiscovery::class)]
#[UsesClass(HyperKernel::class)]
#[UsesClass(\Arcanum\Toolkit\Strings::class)]
final class RoutingTest extends TestCase
{
    /**
     * @param array<string, mixed> $appConfig
     * @param array<string, mixed> $routesConfig
     * @param array<string, mixed> $formatsConfig
     */
    private function buildContainer(array $appConfig, array $routesConfig, array $formatsConfig): Container
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);
        $container->instance(\Psr\Container\ContainerInterface::class, $container);

        $config = new Configuration([
            'app' => $appConfig,
            'routes' => $routesConfig,
            'formats' => $formatsConfig,
        ]);
        $container->instance(Configuration::class, $config);

        $kernel = new HyperKernel(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arcanum_test_' . uniqid());
        $container->instance(Kernel::class, $kernel);

        return $container;
    }

    /**
     * @return array<string, string>
     */
    private function defaultApp(): array
    {
        return [
            'namespace' => 'App',
            'pages_namespace' => 'Arcanum\\Test\\Atlas\\Fixture\\Pages',
            'pages_directory' => dirname(__DIR__, 2) . '/Atlas/Fixture/Pages',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultFormats(): array
    {
        return [
            'default' => 'json',
            'formats' => [
                'json' => [
                    'content_type' => 'application/json',
                    'renderer' => JsonResponseRenderer::class,
                ],
            ],
        ];
    }

    // ---------------------------------------------------------------
    // Registers core services
    // ---------------------------------------------------------------

    public function testRegistersRouter(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultApp(), ['pages' => []], $this->defaultFormats());
        $bootstrapper = new Routing();

        // Act
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(Router::class));
    }

    public function testRegistersConventionResolver(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultApp(), ['pages' => []], $this->defaultFormats());
        $bootstrapper = new Routing();

        // Act
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(ConventionResolver::class));
    }

    public function testRegistersPageResolver(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultApp(), ['pages' => []], $this->defaultFormats());
        $bootstrapper = new Routing();

        // Act
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(PageResolver::class));
    }

    public function testRegistersFormatRegistry(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultApp(), ['pages' => []], $this->defaultFormats());

        // Act
        (new Formats())->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(FormatRegistry::class));
    }

    public function testRegistersHydrator(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultApp(), ['pages' => []], $this->defaultFormats());
        $bootstrapper = new Routing();

        // Act
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(Hydrator::class));
    }

    public function testRegistersJsonRenderer(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultApp(), ['pages' => []], $this->defaultFormats());

        // Act
        (new Formats())->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(JsonResponseRenderer::class));
    }

    // ---------------------------------------------------------------
    // Namespace config
    // ---------------------------------------------------------------

    public function testUsesConfiguredNamespace(): void
    {
        // Arrange
        $app = ['namespace' => 'BoilerRoom', 'pages_namespace' => 'BoilerRoom\\Pages'];
        $container = $this->buildContainer($app, ['pages' => []], $this->defaultFormats());
        $bootstrapper = new Routing();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var ConventionResolver $resolver */
        $resolver = $container->get(ConventionResolver::class);
        $route = $resolver->resolve('/shop/products', 'GET');

        // Assert
        $this->assertSame('BoilerRoom\\Shop\\Query\\Products', $route->dtoClass);
    }

    public function testThrowsWhenNamespaceMissing(): void
    {
        // Arrange
        $container = $this->buildContainer(
            ['pages_namespace' => 'App\\Pages'],
            ['pages' => []],
            $this->defaultFormats(),
        );
        $bootstrapper = new Routing();

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('app.namespace');

        // Act
        $bootstrapper->bootstrap($container);
    }

    public function testThrowsWhenPagesNamespaceMissing(): void
    {
        // Arrange
        $container = $this->buildContainer(
            ['namespace' => 'App'],
            ['pages' => []],
            $this->defaultFormats(),
        );
        $bootstrapper = new Routing();

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('app.pages_namespace');

        // Act
        $bootstrapper->bootstrap($container);
    }

    // ---------------------------------------------------------------
    // Page registration from config
    // ---------------------------------------------------------------

    public function testDiscoversAndRegistersPages(): void
    {
        // Arrange — uses test fixture pages at tests/Atlas/Fixture/Pages/
        $container = $this->buildContainer(
            $this->defaultApp(),
            [],
            $this->defaultFormats(),
        );
        $bootstrapper = new Routing();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var RouteMap $routeMap */
        $routeMap = $container->get(RouteMap::class);

        // Assert — auto-discovered pages registered as custom routes
        $this->assertTrue($routeMap->has('/'));
        $this->assertTrue($routeMap->has('/about-us'));
        $this->assertTrue($routeMap->has('/docs/getting-started'));
        $this->assertFalse($routeMap->has('/nonexistent'));
    }

    public function testPageFormatOverrideFromConfig(): void
    {
        // Arrange — override root page format to json
        $container = $this->buildContainer(
            $this->defaultApp(),
            ['pages' => ['/' => 'json']],
            $this->defaultFormats(),
        );
        $bootstrapper = new Routing();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var RouteMap $routeMap */
        $routeMap = $container->get(RouteMap::class);
        $route = $routeMap->resolve('/', 'GET');

        // Assert
        $this->assertSame('json', $route->format);
    }

    // ---------------------------------------------------------------
    // Format registration from config
    // ---------------------------------------------------------------

    public function testRegistersFormatsFromConfig(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultApp(), ['pages' => []], $this->defaultFormats());

        // Act
        (new Formats())->bootstrap($container);

        /** @var FormatRegistry $formats */
        $formats = $container->get(FormatRegistry::class);

        // Assert
        $this->assertTrue($formats->has('json'));
        $this->assertFalse($formats->has('html'));
    }

    public function testRegistersMultipleFormats(): void
    {
        // Arrange
        $formats = [
            'default' => 'json',
            'formats' => [
                'json' => ['content_type' => 'application/json', 'renderer' => JsonResponseRenderer::class],
                'txt' => ['content_type' => 'text/plain', 'renderer' => 'App\\Shodo\\PlainTextRenderer'],
            ],
        ];
        $container = $this->buildContainer($this->defaultApp(), ['pages' => []], $formats);

        // Act
        (new Formats())->bootstrap($container);

        /** @var FormatRegistry $registry */
        $registry = $container->get(FormatRegistry::class);

        // Assert
        $this->assertTrue($registry->has('json'));
        $this->assertTrue($registry->has('txt'));
    }

    public function testDefaultFormatFromConfig(): void
    {
        // Arrange — use Arcanum\Test\Fixture namespace so the DTO class exists
        $formats = [
            'default' => 'txt',
            'formats' => [
                'txt' => ['content_type' => 'text/plain', 'renderer' => 'App\\Shodo\\PlainTextRenderer'],
            ],
        ];
        $app = [
            'namespace' => 'Arcanum\\Test\\Fixture',
            'pages_namespace' => 'Arcanum\\Test\\Fixture\\Pages',
        ];
        $container = $this->buildContainer($app, ['pages' => []], $formats);
        $bootstrapper = new Routing();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var Router $router */
        $router = $container->get(Router::class);

        // Use a stub request to test the default format
        $uri = $this->createStub(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn('/shop/products');

        $request = $this->createStub(\Psr\Http\Message\ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $route = $router->resolve($request);

        // Assert — no extension, so default format should be 'txt'
        $this->assertSame('txt', $route->format);
    }
}
