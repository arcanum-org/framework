<?php

declare(strict_types=1);

namespace Arcanum\Test\Integration;

use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\HttpRouter;
use Arcanum\Atlas\Route;
use Arcanum\Cabinet\Container;
use Arcanum\Codex\Hydrator;
use Arcanum\Flow\Conveyor\EmptyDTO;
use Arcanum\Flow\Conveyor\MiddlewareBus;
use Arcanum\Flow\Conveyor\QueryResult;
use Arcanum\Shodo\Format;
use Arcanum\Shodo\FormatRegistry;
use Arcanum\Shodo\JsonRenderer;
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

    /**
     * Hydrate a DTO from route and data.
     *
     * @param array<string, mixed> $data
     */
    private function hydrate(Hydrator $hydrator, Route $route, array $data): object
    {
        /** @var class-string $class */
        $class = $route->dtoClass;
        return $hydrator->hydrate($class, $data);
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
        $dto = $this->hydrate($hydrator, $route, $data);

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
        $dto = $this->hydrate($hydrator, $route, $data);

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
        $dto = $this->hydrate($hydrator, $route, $data);
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
        $dto = $this->hydrate($hydrator, $route, $request->getQueryParams());

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
        $dto = $this->hydrate($hydrator, $route, $request->getQueryParams());
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
        $formats->register(new Format('json', 'application/json', JsonRenderer::class));
        $container->service(JsonRenderer::class);

        $request = $this->stubRequest(
            'GET',
            '/integration/status.json',
            queryParams: ['verbose' => 'true'],
        );

        // Act — the full lifecycle
        $route = $router->resolve($request);
        $dto = $this->hydrate($hydrator, $route, $request->getQueryParams());
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
        $formats->register(new Format('json', 'application/json', JsonRenderer::class));
        $container->service(JsonRenderer::class);

        $request = $this->stubRequest('GET', '/integration/status');

        // Act
        $route = $router->resolve($request);
        $dto = $this->hydrate($hydrator, $route, $request->getQueryParams());
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

        $emptyRenderer = new \Arcanum\Shodo\EmptyResponseRenderer();

        $request = $this->stubRequest(
            'PUT',
            '/integration/submit',
            jsonBody: '{"name":"Alice","email":"alice@example.com","message":"Hi"}',
        );

        // Act — the full command lifecycle
        $route = $router->resolve($request);
        $data = (array) ($request->getParsedBody() ?? []);
        $dto = $this->hydrate($hydrator, $route, $data);
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
}
