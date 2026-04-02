<?php

declare(strict_types=1);

namespace Arcanum\Test\Integration;

use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\HttpRouter;
use Arcanum\Atlas\MiddlewareRegistry;
use Arcanum\Atlas\RouteMiddleware;
use Arcanum\Cabinet\Container;
use Arcanum\Codex\Hydrator;
use Arcanum\Flow\Conveyor\EmptyDTO;
use Arcanum\Flow\Conveyor\MiddlewareBus;
use Arcanum\Flow\Conveyor\QueryResult;
use Arcanum\Flow\Continuum\Progression;
use Arcanum\Ignition\RouteDispatcher;
use Arcanum\Hyper\CsvResponseRenderer;
use Arcanum\Hyper\FormatRegistry;
use Arcanum\Hyper\HtmlResponseRenderer;
use Arcanum\Hyper\JsonResponseRenderer;
use Arcanum\Shodo\Formatters\Format;
use Arcanum\Shodo\Formatters\HtmlFallbackFormatter;
use Arcanum\Shodo\Formatters\HtmlFormatter;
use Arcanum\Shodo\TemplateCache;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Shodo\TemplateResolver;
use Arcanum\Test\Fixture\Integration\Command\Submit;
use Arcanum\Test\Fixture\Integration\Query\Status;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Integration tests for the full CQRS request lifecycle.
 *
 * These tests verify that the major framework components work together
 * correctly: Router → Hydrator → Conveyor → Renderer.
 */
#[CoversNothing]
final class CqrsLifecycleTest extends TestCase
{
    private const ROOT_NS = 'Arcanum\\Test\\Fixture';

    /** @param array<string, string> $queryParams */
    private function stubRequest(
        string $method,
        string $path,
        array $queryParams = [],
        string $jsonBody = '',
    ): ServerRequestInterface {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);
        $request->method('getQueryParams')->willReturn($queryParams);

        if ($jsonBody !== '') {
            /** @var array<string, mixed> $parsed */
            $parsed = json_decode($jsonBody, true);
            $request->method('getParsedBody')->willReturn($parsed);
        }

        return $request;
    }

    private function container(): Container
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);
        $container->instance(\Psr\Container\ContainerInterface::class, $container);
        return $container;
    }

    private function router(): HttpRouter
    {
        return new HttpRouter(new ConventionResolver(self::ROOT_NS));
    }

    // -----------------------------------------------------------
    // JSON body → Hydrator → Command DTO
    // -----------------------------------------------------------

    public function testJsonBodyHydratesCommandDto(): void
    {
        // Arrange
        $request = $this->stubRequest(
            'PUT',
            '/integration/submit',
            jsonBody: '{"name":"Alice","email":"alice@example.com","message":"Hello"}',
        );
        $hydrator = new Hydrator();

        // Act — resolve route, hydrate DTO from parsed body
        $route = $this->router()->resolve($request);
        $data = (array) ($request->getParsedBody() ?? []);
        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, $data);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Integration\\Command\\Submit', $route->dtoClass);
        $this->assertInstanceOf(Submit::class, $dto);
        $this->assertSame('Alice', $dto->name);
        $this->assertSame('alice@example.com', $dto->email);
        $this->assertSame('Hello', $dto->message);
    }

    public function testJsonBodyHydratesCommandDtoWithDefaults(): void
    {
        // Arrange — message has a default, so omit it
        $request = $this->stubRequest(
            'PUT',
            '/integration/submit',
            jsonBody: '{"name":"Bob","email":"bob@example.com"}',
        );
        $hydrator = new Hydrator();

        // Act
        $route = $this->router()->resolve($request);
        $data = (array) ($request->getParsedBody() ?? []);
        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, $data);

        // Assert
        $this->assertInstanceOf(Submit::class, $dto);
        $this->assertSame('Bob', $dto->name);
        $this->assertSame('bob@example.com', $dto->email);
        $this->assertSame('', $dto->message);
    }

    public function testCommandDispatchReturnsEmptyDto(): void
    {
        // Arrange
        $container = $this->container();
        $bus = new MiddlewareBus($container);
        $hydrator = new Hydrator();

        $request = $this->stubRequest(
            'PUT',
            '/integration/submit',
            jsonBody: '{"name":"Alice","email":"alice@example.com"}',
        );

        // Act — resolve, hydrate, dispatch
        $route = $this->router()->resolve($request);
        $data = (array) ($request->getParsedBody() ?? []);
        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, $data);
        $result = $bus->dispatch($dto, prefix: $route->handlerPrefix);

        // Assert — void handler returns EmptyDTO
        $this->assertInstanceOf(EmptyDTO::class, $result);
    }

    // -----------------------------------------------------------
    // Query params → Hydrator → Query DTO → Handler → Response
    // -----------------------------------------------------------

    public function testQueryParamsHydrateQueryDto(): void
    {
        // Arrange
        $request = $this->stubRequest(
            'GET',
            '/integration/status',
            queryParams: ['verbose' => 'true'],
        );
        $hydrator = new Hydrator();

        // Act
        $route = $this->router()->resolve($request);
        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, $request->getQueryParams());

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Integration\\Query\\Status', $route->dtoClass);
        $this->assertInstanceOf(Status::class, $dto);
        $this->assertTrue($dto->verbose);
    }

    public function testQueryDispatchReturnsWrappedResult(): void
    {
        // Arrange
        $container = $this->container();
        $bus = new MiddlewareBus($container);
        $hydrator = new Hydrator();

        $request = $this->stubRequest(
            'GET',
            '/integration/status',
            queryParams: ['verbose' => 'true'],
        );

        // Act — resolve, hydrate, dispatch
        $route = $this->router()->resolve($request);
        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, $request->getQueryParams());
        $result = $bus->dispatch($dto, prefix: $route->handlerPrefix);

        // Assert — array handler result is wrapped in QueryResult
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertSame(['status' => 'ok', 'version' => '1.0.0', 'uptime' => 42], $result->data);
    }

    // -----------------------------------------------------------
    // Full query response: Route → Hydrate → Dispatch → Render
    // -----------------------------------------------------------

    public function testFullQueryLifecycleProducesJsonResponse(): void
    {
        // Arrange
        $container = $this->container();
        $bus = new MiddlewareBus($container);
        $hydrator = new Hydrator();
        $router = $this->router();

        $formats = new FormatRegistry($container);
        $formats->register(new Format('json', 'application/json', JsonResponseRenderer::class));
        $container->service(JsonResponseRenderer::class);

        $request = $this->stubRequest(
            'GET',
            '/integration/status.json',
            queryParams: ['verbose' => 'true'],
        );

        // Act — the full lifecycle
        $route = $router->resolve($request);
        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, $request->getQueryParams());
        $result = $bus->dispatch($dto, prefix: $route->handlerPrefix);

        // Unwrap QueryResult
        $data = $result instanceof QueryResult ? $result->data : $result;

        /** @var ResponseInterface $response */
        $response = $formats->renderer($route->format)->render($data);

        // Assert — complete JSON response
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));

        /** @var array<string, mixed> $body */
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame('ok', $body['status']);
        $this->assertSame('1.0.0', $body['version']);
        $this->assertSame(42, $body['uptime']);
    }

    public function testFullQueryLifecycleWithDefaultParams(): void
    {
        // Arrange
        $container = $this->container();
        $bus = new MiddlewareBus($container);
        $hydrator = new Hydrator();
        $router = $this->router();

        $formats = new FormatRegistry($container);
        $formats->register(new Format('json', 'application/json', JsonResponseRenderer::class));
        $container->service(JsonResponseRenderer::class);

        $request = $this->stubRequest('GET', '/integration/status');

        // Act
        $route = $router->resolve($request);
        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, $request->getQueryParams());
        $result = $bus->dispatch($dto, prefix: $route->handlerPrefix);
        $data = $result instanceof QueryResult ? $result->data : $result;

        /** @var ResponseInterface $response */
        $response = $formats->renderer($route->format)->render($data);

        // Assert — verbose=false means minimal response
        $body = json_decode((string) $response->getBody(), true);
        $this->assertSame(['status' => 'ok'], $body);
    }

    // -----------------------------------------------------------
    // Full command lifecycle: Route → Hydrate → Dispatch → Status
    // -----------------------------------------------------------

    public function testFullCommandLifecycleProduces204(): void
    {
        // Arrange
        $container = $this->container();
        $bus = new MiddlewareBus($container);
        $hydrator = new Hydrator();
        $router = $this->router();

        $emptyRenderer = new \Arcanum\Hyper\EmptyResponseRenderer();

        $request = $this->stubRequest(
            'PUT',
            '/integration/submit',
            jsonBody: '{"name":"Alice","email":"alice@example.com","message":"Hi"}',
        );

        // Act — the full command lifecycle
        $route = $router->resolve($request);
        $data = (array) ($request->getParsedBody() ?? []);
        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, $data);
        $result = $bus->dispatch($dto, prefix: $route->handlerPrefix);

        // Determine status code from result type
        $status = $result instanceof EmptyDTO
            ? \Arcanum\Hyper\StatusCode::NoContent
            : \Arcanum\Hyper\StatusCode::Created;

        $response = $emptyRenderer->render($status);

        // Assert — void handler → 204 No Content, empty body
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('', (string) $response->getBody());
    }

    // -----------------------------------------------------------
    // Full query lifecycle: HTML format
    // -----------------------------------------------------------

    public function testFullQueryLifecycleProducesHtmlResponse(): void
    {
        // Arrange
        $container = $this->container();
        $bus = new MiddlewareBus($container);
        $hydrator = new Hydrator();
        $router = $this->router();

        $formats = new FormatRegistry($container);
        $formats->register(new Format('html', 'text/html', HtmlResponseRenderer::class));

        // Wire up HtmlResponseRenderer — TemplateResolver points at a nonexistent dir
        // so it falls back to HtmlFallbackFormatter for a generic HTML representation.
        $container->factory(HtmlResponseRenderer::class, function () {
            $formatter = new HtmlFormatter(
                resolver: new TemplateResolver('/nonexistent', 'Arcanum\Test'),
                compiler: new TemplateCompiler(),
                cache: new TemplateCache(''),
                fallback: new HtmlFallbackFormatter(),
            );
            return new HtmlResponseRenderer($formatter);
        });

        $request = $this->stubRequest(
            'GET',
            '/integration/status.html',
            queryParams: ['verbose' => 'true'],
        );

        // Act — full lifecycle: Route → Hydrate → Dispatch → Render
        $route = $router->resolve($request);
        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, $request->getQueryParams());
        $result = $bus->dispatch($dto, prefix: $route->handlerPrefix);
        $data = $result instanceof QueryResult ? $result->data : $result;

        /** @var HtmlResponseRenderer $renderer */
        $renderer = $formats->renderer($route->format);
        $response = $renderer->render($data, $route->dtoClass);

        // Assert
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html; charset=UTF-8', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $this->assertStringContainsString('<!DOCTYPE html>', $body);
        $this->assertStringContainsString('ok', $body);
        $this->assertStringContainsString('1.0.0', $body);
    }

    // -----------------------------------------------------------
    // Full query lifecycle: CSV format
    // -----------------------------------------------------------

    public function testFullQueryLifecycleProducesCsvResponse(): void
    {
        // Arrange
        $container = $this->container();
        $bus = new MiddlewareBus($container);
        $hydrator = new Hydrator();
        $router = $this->router();

        $formats = new FormatRegistry($container);
        $formats->register(new Format('csv', 'text/csv', CsvResponseRenderer::class));
        $container->service(CsvResponseRenderer::class);

        $request = $this->stubRequest(
            'GET',
            '/integration/status.csv',
            queryParams: ['verbose' => 'true'],
        );

        // Act — full lifecycle: Route → Hydrate → Dispatch → Render
        $route = $router->resolve($request);
        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, $request->getQueryParams());
        $result = $bus->dispatch($dto, prefix: $route->handlerPrefix);
        $data = $result instanceof QueryResult ? $result->data : $result;

        /** @var ResponseInterface $response */
        $response = $formats->renderer($route->format)->render($data);

        // Assert
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/csv; charset=UTF-8', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        // Associative array renders as key,value CSV
        $this->assertStringContainsString('key,value', $body);
        $this->assertStringContainsString('status,ok', $body);
        $this->assertStringContainsString('version,1.0.0', $body);
        $this->assertStringContainsString('uptime,42', $body);
    }

    // -----------------------------------------------------------
    // Per-route middleware via RouteDispatcher
    // -----------------------------------------------------------

    public function testRouteDispatcherAppliesBeforeMiddleware(): void
    {
        // Arrange — before middleware that adds a tag to the DTO
        $container = $this->container();
        $bus = new MiddlewareBus($container);
        $hydrator = new Hydrator();
        $router = $this->router();

        $registry = new MiddlewareRegistry();
        $registry->register(
            self::ROOT_NS . '\\Integration\\Query\\Status',
            new RouteMiddleware(before: ['test.before']),
        );

        // Register a before progression that marks execution
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

        $request = $this->stubRequest('GET', '/integration/status');
        $route = $router->resolve($request);

        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, $request->getQueryParams());

        // Act
        $result = $dispatcher->dispatch($dto, $route);

        // Assert — before middleware ran, query still produced a result
        $this->assertTrue($beforeRan);
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertSame(['status' => 'ok'], $result->data);
    }

    public function testRouteDispatcherSkipsMiddlewareWhenNoneRegistered(): void
    {
        // Arrange — empty registry, should behave identically to direct bus dispatch
        $container = $this->container();
        $bus = new MiddlewareBus($container);
        $hydrator = new Hydrator();
        $router = $this->router();

        $registry = new MiddlewareRegistry();
        $dispatcher = new RouteDispatcher($container, $registry, $bus);

        $request = $this->stubRequest('GET', '/integration/status', queryParams: ['verbose' => 'true']);
        $route = $router->resolve($request);

        /** @var class-string $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $hydrator->hydrate($dtoClass, $request->getQueryParams());

        // Act
        $result = $dispatcher->dispatch($dto, $route);

        // Assert
        $this->assertInstanceOf(QueryResult::class, $result);
        $this->assertSame(['status' => 'ok', 'version' => '1.0.0', 'uptime' => 42], $result->data);
    }
}
