<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper\Middleware;

use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\HttpRouter;
use Arcanum\Atlas\PageResolver;
use Arcanum\Atlas\Route;
use Arcanum\Flow\River\EmptyStream;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Middleware\Options;
use Arcanum\Hyper\Response;
use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Version;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(Options::class)]
#[UsesClass(ConventionResolver::class)]
#[UsesClass(EmptyStream::class)]
#[UsesClass(Headers::class)]
#[UsesClass(HttpRouter::class)]
#[UsesClass(Message::class)]
#[UsesClass(PageResolver::class)]
#[UsesClass(Response::class)]
#[UsesClass(Route::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(\Arcanum\Hyper\Phrase::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
#[UsesClass(\Arcanum\Gather\IgnoreCaseRegistry::class)]
#[UsesClass(\Arcanum\Toolkit\Strings::class)]
final class OptionsTest extends TestCase
{
    private const ROOT_NS = 'Arcanum\\Test\\Fixture';
    private const PAGES_NS = 'Arcanum\\Test\\Fixture\\Pages';

    private function stubRequest(string $method, string $path): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    private function router(): HttpRouter
    {
        return new HttpRouter(new ConventionResolver(self::ROOT_NS));
    }

    private function routerWithPages(): HttpRouter
    {
        $pages = new PageResolver(namespace: self::PAGES_NS);
        $pages->register('/');
        $pages->register('/thing');
        return new HttpRouter(new ConventionResolver(self::ROOT_NS), $pages);
    }

    // -----------------------------------------------------------
    // OPTIONS with allowed methods — 204 + Allow header
    // -----------------------------------------------------------

    public function testOptionsReturns204WithAllowHeader(): void
    {
        // Arrange — Shop has both Query\Products and Command\Products
        $middleware = new Options($this->router());

        // Act
        $response = $middleware->process(
            $this->stubRequest('OPTIONS', '/shop/products'),
            $this->createStub(RequestHandlerInterface::class),
        );

        // Assert
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame(
            'GET, PUT, POST, PATCH, DELETE, OPTIONS',
            $response->getHeaderLine('Allow'),
        );
    }

    public function testOptionsForQueryOnlyPath(): void
    {
        // Arrange — Reports\Query\Summary exists, no Command
        $middleware = new Options($this->router());

        // Act
        $response = $middleware->process(
            $this->stubRequest('OPTIONS', '/reports/summary'),
            $this->createStub(RequestHandlerInterface::class),
        );

        // Assert
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('GET, OPTIONS', $response->getHeaderLine('Allow'));
    }

    public function testOptionsForCommandOnlyPath(): void
    {
        // Arrange — Contact\Command\Submit exists, no Query
        $middleware = new Options($this->router());

        // Act
        $response = $middleware->process(
            $this->stubRequest('OPTIONS', '/contact/submit'),
            $this->createStub(RequestHandlerInterface::class),
        );

        // Assert
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame(
            'PUT, POST, PATCH, DELETE, OPTIONS',
            $response->getHeaderLine('Allow'),
        );
    }

    public function testOptionsForPage(): void
    {
        // Arrange — root "/" is a registered page
        $middleware = new Options($this->routerWithPages());

        // Act
        $response = $middleware->process(
            $this->stubRequest('OPTIONS', '/'),
            $this->createStub(RequestHandlerInterface::class),
        );

        // Assert
        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame('GET, OPTIONS', $response->getHeaderLine('Allow'));
    }

    // -----------------------------------------------------------
    // OPTIONS for non-existent path — delegates to handler
    // -----------------------------------------------------------

    public function testOptionsDelegatesToHandlerForNonExistentPath(): void
    {
        // Arrange — /nowhere resolves to nothing
        $middleware = new Options($this->router());

        $handlerResponse = $this->createStub(ResponseInterface::class);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($handlerResponse);

        // Act
        $response = $middleware->process(
            $this->stubRequest('OPTIONS', '/nowhere/nothing'),
            $handler,
        );

        // Assert — handler's response (likely a 404), not a 204
        $this->assertSame($handlerResponse, $response);
    }

    // -----------------------------------------------------------
    // Non-OPTIONS requests — delegates to handler
    // -----------------------------------------------------------

    public function testNonOptionsRequestDelegatesToHandler(): void
    {
        // Arrange
        $middleware = new Options($this->router());

        $handlerResponse = $this->createStub(ResponseInterface::class);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($handlerResponse);

        // Act
        $response = $middleware->process(
            $this->stubRequest('GET', '/shop/products'),
            $handler,
        );

        // Assert
        $this->assertSame($handlerResponse, $response);
    }

    // -----------------------------------------------------------
    // Response body is empty
    // -----------------------------------------------------------

    public function testOptionsResponseHasEmptyBody(): void
    {
        // Arrange
        $middleware = new Options($this->router());

        // Act
        $response = $middleware->process(
            $this->stubRequest('OPTIONS', '/shop/products'),
            $this->createStub(RequestHandlerInterface::class),
        );

        // Assert
        $this->assertSame('', (string) $response->getBody());
    }
}
