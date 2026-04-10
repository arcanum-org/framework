<?php

declare(strict_types=1);

namespace Arcanum\Test\Integration;

use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\HttpRouter;
use Arcanum\Atlas\LocationResolver;
use Arcanum\Atlas\MiddlewareRegistry;
use Arcanum\Atlas\Router;
use Arcanum\Atlas\RouteMiddleware;
use Arcanum\Atlas\UrlResolver;
use Arcanum\Cabinet\Application;
use Arcanum\Codex\Hydrator;
use Arcanum\Flow\Continuum\Progression;
use Arcanum\Flow\Conveyor\Bus;
use Arcanum\Flow\Conveyor\MiddlewareBus;
use Arcanum\Flow\Conveyor\QueryResult;
use Arcanum\Hyper\CsvResponseRenderer;
use Arcanum\Hyper\FormatRegistry;
use Arcanum\Hyper\HtmlResponseRenderer;
use Arcanum\Hyper\JsonResponseRenderer;
use Arcanum\Ignition\RouteDispatcher;
use Arcanum\Shodo\Format;
use Arcanum\Shodo\Formatters\HtmlFallbackFormatter;
use Arcanum\Shodo\Formatters\HtmlFormatter;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateEngine;
use Arcanum\Shodo\TemplateResolver;
use Arcanum\Test\Fixture\Integration\RoutingHandler;
use Arcanum\Testing\TestKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Integration tests for the full CQRS request lifecycle, dispatched through
 * `TestKernel->http()`. The wrapped HyperKernel runs through its real
 * exception/middleware/lifecycle paths; the `RoutingHandler` fixture wires up
 * Route → Hydrate → Dispatch → Render the way the production `Routing`
 * bootstrapper would.
 *
 * RouteDispatcher-specific tests at the bottom still construct their pieces
 * directly because they're testing per-route middleware composition, not the
 * HTTP boundary.
 */
#[CoversNothing]
final class CqrsLifecycleTest extends TestCase
{
    private const ROOT_NS = 'Arcanum\\Test\\Fixture';

    /**
     * Build a TestKernel with the CQRS pipeline wired up against its
     * shared container, ready to dispatch through `http()`.
     */
    private function buildKernel(): TestKernel
    {
        $kernel = new TestKernel();
        $container = $kernel->container();

        $container->instance(Application::class, $container);

        $hydrator = new Hydrator();
        $bus = new MiddlewareBus($container);
        $router = new HttpRouter(new ConventionResolver(self::ROOT_NS));

        $formats = new FormatRegistry($container);
        $formats->register(new Format('json', 'application/json', JsonResponseRenderer::class));
        $formats->register(new Format('csv', 'text/csv', CsvResponseRenderer::class));
        $formats->register(new Format('html', 'text/html', HtmlResponseRenderer::class));

        $container->service(JsonResponseRenderer::class);
        $container->service(CsvResponseRenderer::class);
        $container->factory(HtmlResponseRenderer::class, function () {
            $resolver = new TemplateResolver('/nonexistent', 'Arcanum\Test');
            $formatter = new HtmlFormatter(
                engine: new TemplateEngine(
                    compiler: new TemplateCompiler(),
                    cache: new TemplateCache(''),
                ),
                fallback: new HtmlFallbackFormatter(),
            );
            return new HtmlResponseRenderer($formatter, $resolver);
        });

        $locationResolver = new LocationResolver(
            urlResolver: new UrlResolver(self::ROOT_NS),
            baseUrl: 'https://api.example.com',
        );

        $kernel->http()->setCoreHandler(
            new RoutingHandler($router, $hydrator, $bus, $formats, $locationResolver),
        );

        return $kernel;
    }

    // -----------------------------------------------------------
    // Full query lifecycle
    // -----------------------------------------------------------

    public function testJsonResponseFromQueryHandler(): void
    {
        $kernel = $this->buildKernel();

        $response = $kernel->http()->get('/integration/status.json?verbose=true');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('ok', $body['status']);
        $this->assertSame('1.0.0', $body['version']);
        $this->assertSame(42, $body['uptime']);
    }

    public function testQueryWithDefaultParams(): void
    {
        $kernel = $this->buildKernel();

        $response = $kernel->http()->get('/integration/status.json');

        $this->assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(['status' => 'ok'], $body);
    }

    public function testHtmlResponseFromQueryHandler(): void
    {
        $kernel = $this->buildKernel();

        $response = $kernel->http()->get('/integration/status.html?verbose=true');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('ok', $body);
        $this->assertStringContainsString('1.0.0', $body);
    }

    public function testCsvResponseFromQueryHandler(): void
    {
        $kernel = $this->buildKernel();

        $response = $kernel->http()->get('/integration/status.csv?verbose=true');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/csv; charset=UTF-8', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $this->assertStringContainsString('key,value', $body);
        $this->assertStringContainsString('status,ok', $body);
        $this->assertStringContainsString('version,1.0.0', $body);
        $this->assertStringContainsString('uptime,42', $body);
    }

    // -----------------------------------------------------------
    // Full command lifecycle
    // -----------------------------------------------------------

    public function testVoidCommandReturns204(): void
    {
        $kernel = $this->buildKernel();

        $response = $kernel->http()
            ->withHeader('Content-Type', 'application/json')
            ->put('/integration/submit', '{"name":"Alice","email":"alice@example.com","message":"Hi"}');

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
        $this->assertSame('', $response->getHeaderLine('Location'));
    }

    public function testVoidCommandHydratesDefaultsForOmittedFields(): void
    {
        // The Submit command's $message has a default; the Hydrator should
        // honor it when the field is missing from the JSON body.
        $kernel = $this->buildKernel();

        $response = $kernel->http()
            ->withHeader('Content-Type', 'application/json')
            ->put('/integration/submit', '{"name":"Bob","email":"bob@example.com"}');

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testCommandReturningQueryDtoProduces201WithLocation(): void
    {
        $kernel = $this->buildKernel();

        $response = $kernel->http()
            ->withHeader('Content-Type', 'application/json')
            ->put('/integration/create-item', '{"name":"Widget"}');

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(
            'https://api.example.com/integration/status?verbose=1',
            $response->getHeaderLine('Location'),
        );
        $this->assertSame('', (string) $response->getBody());
    }

    // -----------------------------------------------------------
    // Per-route middleware via RouteDispatcher
    //
    // These tests intentionally bypass the HTTP boundary — they're
    // testing how RouteDispatcher composes per-route before/after
    // middleware around the bus, not how the HTTP request lifecycle
    // works. The full-lifecycle tests above prove that path.
    // -----------------------------------------------------------

    private function stubGetRequest(string $path): ServerRequestInterface
    {
        $uri = $this->createStub(\Psr\Http\Message\UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);
        $request->method('getQueryParams')->willReturn([]);

        return $request;
    }

    public function testRouteDispatcherAppliesBeforeMiddleware(): void
    {
        $kernel = new TestKernel();
        $container = $kernel->container();
        $container->instance(Application::class, $container);

        $bus = new MiddlewareBus($container);
        $hydrator = new Hydrator();
        $router = new HttpRouter(new ConventionResolver(self::ROOT_NS));

        $registry = new MiddlewareRegistry();
        $registry->register(
            self::ROOT_NS . '\\Integration\\Query\\Status',
            new RouteMiddleware(before: ['test.before']),
        );

        $beforeRan = false;
        $container->instance('test.before', new class ($beforeRan) implements Progression {
            public function __construct(public bool &$ran)
            {
            }

            public function __invoke(object $payload, callable $next): void
            {
                $this->ran = true;
                $next();
            }
        });

        $dispatcher = new RouteDispatcher($container, $registry, $bus);

        $request = $this->stubGetRequest('/integration/status');
        $route = $router->resolve($request);

        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, $request->getQueryParams());

        $result = $dispatcher->dispatch($dto, $route);

        $this->assertTrue($beforeRan);
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertSame(['status' => 'ok'], $result->data);
    }

    public function testRouteDispatcherSkipsMiddlewareWhenNoneRegistered(): void
    {
        $kernel = new TestKernel();
        $container = $kernel->container();
        $container->instance(Application::class, $container);

        $bus = new MiddlewareBus($container);
        $hydrator = new Hydrator();
        $router = new HttpRouter(new ConventionResolver(self::ROOT_NS));

        $registry = new MiddlewareRegistry();
        $dispatcher = new RouteDispatcher($container, $registry, $bus);

        $request = $this->stubGetRequest('/integration/status');
        $route = $router->resolve($request);

        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, ['verbose' => 'true']);

        $result = $dispatcher->dispatch($dto, $route);

        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertSame(['status' => 'ok', 'version' => '1.0.0', 'uptime' => 42], $result->data);
    }
}
