<?php

declare(strict_types=1);

namespace Arcanum\Test\Fixture\Integration;

use Arcanum\Atlas\LocationResolver;
use Arcanum\Atlas\Router;
use Arcanum\Codex\Hydrator;
use Arcanum\Flow\Conveyor\AcceptedDTO;
use Arcanum\Flow\Conveyor\Bus;
use Arcanum\Flow\Conveyor\EmptyDTO;
use Arcanum\Flow\Conveyor\QueryResult;
use Arcanum\Hyper\EmptyResponseRenderer;
use Arcanum\Hyper\FormatRegistry;
use Arcanum\Hyper\HtmlResponseRenderer;
use Arcanum\Hyper\StatusCode;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Test fixture: a minimal Route → Hydrate → Dispatch → Render core handler.
 *
 * Mirrors the production CQRS pipeline that the framework's `Routing`
 * bootstrapper wires up, but constructed by hand so it can be installed via
 * `HttpTestSurface::setCoreHandler()` without standing up the full bootstrap
 * chain. Used by `CqrsLifecycleTest` to exercise the lifecycle through
 * `TestKernel->http()` instead of hand-rolling each piece per test.
 */
final class RoutingHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Router $router,
        private readonly Hydrator $hydrator,
        private readonly Bus $bus,
        private readonly FormatRegistry $formats,
        private readonly LocationResolver|null $locationResolver = null,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $this->router->resolve($request);

        $data = $request->getMethod() === 'GET'
            ? $request->getQueryParams()
            : (array) ($request->getParsedBody() ?? []);

        /** @var class-string<object> $dtoClass */
        $dtoClass = $route->dtoClass;
        $dto = $this->hydrator->hydrate($dtoClass, $data);

        $result = $this->bus->dispatch($dto, prefix: $route->handlerPrefix);

        $emptyRenderer = new EmptyResponseRenderer();

        if ($result instanceof EmptyDTO) {
            return $emptyRenderer->render(StatusCode::NoContent);
        }

        if ($result instanceof AcceptedDTO) {
            return $emptyRenderer->render(StatusCode::Accepted);
        }

        if (!$result instanceof QueryResult) {
            // Command returned a Query DTO → 201 Created with Location header.
            $response = $emptyRenderer->render(StatusCode::Created);
            if ($this->locationResolver !== null) {
                $location = $this->locationResolver->resolve($result);
                if ($location !== null) {
                    $response = $response->withHeader('Location', $location);
                }
            }
            return $response;
        }

        $payload = $result->data;

        $renderer = $this->formats->renderer($route->format);

        if ($renderer instanceof HtmlResponseRenderer) {
            return $renderer->render($payload, $route->dtoClass);
        }

        return $renderer->render($payload);
    }
}
