<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas;

use Arcanum\Atlas\Attribute\AllowedFormats;
use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\HttpRouter;
use Arcanum\Atlas\MethodNotAllowed;
use Arcanum\Atlas\PageResolver;
use Arcanum\Atlas\Route;
use Arcanum\Atlas\Router;
use Arcanum\Atlas\UnresolvableRoute;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

#[CoversClass(HttpRouter::class)]
#[UsesClass(AllowedFormats::class)]
#[UsesClass(ConventionResolver::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(MethodNotAllowed::class)]
#[UsesClass(PageResolver::class)]
#[UsesClass(Route::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Strings::class)]
#[UsesClass(UnresolvableRoute::class)]
final class HttpRouterTest extends TestCase
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
        $pages->register('/docs/getting-started');

        return new HttpRouter(new ConventionResolver(self::ROOT_NS), pages: $pages);
    }

    // ---------------------------------------------------------------
    // Basic resolution through to ConventionResolver
    // ---------------------------------------------------------------

    public function testResolvesGetRequestToQuery(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/shop/new-products');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Shop\\Query\\NewProducts', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
        $this->assertTrue($route->isQuery());
    }

    public function testResolvesPutRequestToCommand(): void
    {
        // Arrange
        $request = $this->stubRequest('PUT', '/checkout/submit-payment');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
        $this->assertTrue($route->isCommand());
    }

    public function testResolvesPostRequestWithPrefix(): void
    {
        // Arrange
        $request = $this->stubRequest('POST', '/checkout/submit-payment');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('Post', $route->handlerPrefix);
    }

    public function testResolvesDeleteRequestWithPrefix(): void
    {
        // Arrange
        $request = $this->stubRequest('DELETE', '/orders/cancel');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Orders\\Command\\Cancel', $route->dtoClass);
        $this->assertSame('Delete', $route->handlerPrefix);
    }

    public function testResolvesPatchRequestWithPrefix(): void
    {
        // Arrange
        $request = $this->stubRequest('PATCH', '/orders/update-address');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Orders\\Command\\UpdateAddress', $route->dtoClass);
        $this->assertSame('Patch', $route->handlerPrefix);
    }

    // ---------------------------------------------------------------
    // Extension parsing — same handler regardless of extension
    // ---------------------------------------------------------------

    public function testJsonExtensionStrippedAndFormatSet(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/shop/new-products.json');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Shop\\Query\\NewProducts', $route->dtoClass);
        $this->assertSame('json', $route->format);
    }

    public function testHtmlExtensionStrippedAndFormatSet(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/shop/new-products.html');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Shop\\Query\\NewProducts', $route->dtoClass);
        $this->assertSame('html', $route->format);
    }

    public function testCsvExtensionStrippedAndFormatSet(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/shop/new-products.csv');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Shop\\Query\\NewProducts', $route->dtoClass);
        $this->assertSame('csv', $route->format);
    }

    public function testSameHandlerResolvedRegardlessOfExtension(): void
    {
        // Arrange & Act
        $router = $this->router();
        $json = $router->resolve($this->stubRequest('GET', '/shop/new-products.json'));
        $html = $router->resolve($this->stubRequest('GET', '/shop/new-products.html'));
        $csv = $router->resolve($this->stubRequest('GET', '/shop/new-products.csv'));
        $noExt = $router->resolve($this->stubRequest('GET', '/shop/new-products'));

        // Assert
        $this->assertSame($json->dtoClass, $html->dtoClass);
        $this->assertSame($json->dtoClass, $csv->dtoClass);
        $this->assertSame($json->dtoClass, $noExt->dtoClass);
    }

    // ---------------------------------------------------------------
    // Format extraction
    // ---------------------------------------------------------------

    public function testFormatIsAvailableOnRoute(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/catalog/products.csv');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame('csv', $route->format);
    }

    public function testExtensionIsLowercased(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/shop/products.JSON');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame('json', $route->format);
    }

    // ---------------------------------------------------------------
    // Missing extension — fallback format
    // ---------------------------------------------------------------

    public function testMissingExtensionUsesDefaultFormat(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/shop/products');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame('json', $route->format);
    }

    public function testDefaultFormatIsConfigurable(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(self::ROOT_NS), defaultFormat: 'html');
        $request = $this->stubRequest('GET', '/shop/products');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('html', $route->format);
    }

    public function testConfiguredDefaultDoesNotOverrideExplicitExtension(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(self::ROOT_NS), defaultFormat: 'html');
        $request = $this->stubRequest('GET', '/shop/products.csv');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('csv', $route->format);
    }

    // ---------------------------------------------------------------
    // Edge cases in extension parsing
    // ---------------------------------------------------------------

    public function testPathWithTrailingSlashAndNoExtension(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/shop/products/');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Shop\\Query\\Products', $route->dtoClass);
        $this->assertSame('json', $route->format);
    }

    // ---------------------------------------------------------------
    // Non-ServerRequestInterface input
    // ---------------------------------------------------------------

    public function testNonServerRequestThrowsUnresolvableRoute(): void
    {
        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $this->expectExceptionMessage('HttpRouter expects a ServerRequestInterface');
        $this->router()->resolve(new \stdClass());
    }

    // ---------------------------------------------------------------
    // Root path
    // ---------------------------------------------------------------

    public function testRootPathThrowsUnresolvableRoute(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/');

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $this->router()->resolve($request);
    }

    // ---------------------------------------------------------------
    // Full plan examples
    // ---------------------------------------------------------------

    public function testPlanExampleGetCatalogFeaturedJson(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/catalog/products/featured.json');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Catalog\\Query\\Products\\Featured', $route->dtoClass);
        $this->assertSame('json', $route->format);
        $this->assertSame('', $route->handlerPrefix);
    }

    public function testPlanExamplePutCheckoutSubmitPayment(): void
    {
        // Arrange
        $request = $this->stubRequest('PUT', '/checkout/submit-payment');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
    }

    public function testPlanExampleDeleteCheckoutSubmitPayment(): void
    {
        // Arrange
        $request = $this->stubRequest('DELETE', '/checkout/submit-payment');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('Delete', $route->handlerPrefix);
    }

    // ---------------------------------------------------------------
    // Pages integration
    // ---------------------------------------------------------------

    public function testRootPathResolvesToPageWhenRegistered(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/');

        // Act
        $route = $this->routerWithPages()->resolve($request);

        // Assert
        $this->assertSame(self::PAGES_NS . '\\Index', $route->dtoClass);
        $this->assertSame('html', $route->format);
    }

    public function testPageResolvedBeforeConvention(): void
    {
        // Arrange — '/thing' is registered as a page, so it should resolve
        // to Pages\Thing, not Query\Thing
        $request = $this->stubRequest('GET', '/thing');

        // Act
        $route = $this->routerWithPages()->resolve($request);

        // Assert
        $this->assertSame(self::PAGES_NS . '\\Thing', $route->dtoClass);
    }

    public function testPageWithExtensionUsesExtensionFormat(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/thing.json');

        // Act
        $route = $this->routerWithPages()->resolve($request);

        // Assert
        $this->assertSame(self::PAGES_NS . '\\Thing', $route->dtoClass);
        $this->assertSame('json', $route->format);
    }

    public function testPageWithoutExtensionUsesPageDefaultFormat(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/thing');

        // Act
        $route = $this->routerWithPages()->resolve($request);

        // Assert
        $this->assertSame('html', $route->format);
    }

    public function testUnregisteredPathFallsBackToConvention(): void
    {
        // Arrange — '/shop/products' is not a page, so convention routing kicks in
        $request = $this->stubRequest('GET', '/shop/products');

        // Act
        $route = $this->routerWithPages()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Shop\\Query\\Products', $route->dtoClass);
    }

    public function testRootPathWithoutPagesThrowsUnresolvableRoute(): void
    {
        // Arrange — no pages registered, root path has no convention mapping
        $request = $this->stubRequest('GET', '/');

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $this->router()->resolve($request);
    }

    public function testNestedPageResolvedViaHttpRouter(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/docs/getting-started');

        // Act
        $route = $this->routerWithPages()->resolve($request);

        // Assert
        $this->assertSame(self::PAGES_NS . '\\Docs\\GettingStarted', $route->dtoClass);
    }

    // ---------------------------------------------------------------
    // HTTP method enforcement — pages are GET-only
    // ---------------------------------------------------------------

    public function testPostToPageThrowsMethodNotAllowed(): void
    {
        // Arrange
        $request = $this->stubRequest('POST', '/');

        // Act & Assert
        $this->expectException(MethodNotAllowed::class);
        $this->routerWithPages()->resolve($request);
    }

    public function testPutToPageThrowsMethodNotAllowed(): void
    {
        // Arrange
        $request = $this->stubRequest('PUT', '/');

        // Act & Assert
        $this->expectException(MethodNotAllowed::class);
        $this->routerWithPages()->resolve($request);
    }

    public function testDeleteToPageThrowsMethodNotAllowed(): void
    {
        // Arrange
        $request = $this->stubRequest('DELETE', '/');

        // Act & Assert
        $this->expectException(MethodNotAllowed::class);
        $this->routerWithPages()->resolve($request);
    }

    public function testPageMethodNotAllowedListsGetAsAllowed(): void
    {
        // Arrange
        $request = $this->stubRequest('POST', '/');

        // Act
        try {
            $this->routerWithPages()->resolve($request);
            $this->fail('Expected MethodNotAllowed exception');
        } catch (MethodNotAllowed $e) {
            // Assert
            $this->assertSame(['GET'], $e->getAllowedMethods());
        }
    }

    // ---------------------------------------------------------------
    // HTTP method enforcement — convention routes
    // ---------------------------------------------------------------

    public function testGetToCommandOnlyPathThrowsMethodNotAllowed(): void
    {
        // Arrange — Contact\Command\Submit exists, Contact\Query\Submit does not
        $request = $this->stubRequest('GET', '/contact/submit');

        // Act & Assert
        $this->expectException(MethodNotAllowed::class);
        $this->router()->resolve($request);
    }

    public function testGetToCommandOnlyPathListsCommandMethodsAsAllowed(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/contact/submit');

        // Act
        try {
            $this->router()->resolve($request);
            $this->fail('Expected MethodNotAllowed exception');
        } catch (MethodNotAllowed $e) {
            // Assert
            $this->assertSame(['PUT', 'POST', 'PATCH', 'DELETE'], $e->getAllowedMethods());
        }
    }

    public function testGetToPathWithBothQueryAndCommandResolvesQuery(): void
    {
        // Arrange — Shop\Query\Products and Shop\Command\Products both exist
        $request = $this->stubRequest('GET', '/shop/products');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Shop\\Query\\Products', $route->dtoClass);
        $this->assertTrue($route->isQuery());
    }

    public function testPutToPathWithBothQueryAndCommandResolvesCommand(): void
    {
        // Arrange — Shop\Query\Products and Shop\Command\Products both exist
        $request = $this->stubRequest('PUT', '/shop/products');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Shop\\Command\\Products', $route->dtoClass);
        $this->assertTrue($route->isCommand());
    }

    public function testPutToQueryOnlyPathThrowsMethodNotAllowed(): void
    {
        // Arrange — Reports\Query\Summary exists, Reports\Command\Summary does not
        $request = $this->stubRequest('PUT', '/reports/summary');

        // Act & Assert
        $this->expectException(MethodNotAllowed::class);
        $this->router()->resolve($request);
    }

    public function testPutToQueryOnlyPathListsGetAsAllowed(): void
    {
        // Arrange
        $request = $this->stubRequest('PUT', '/reports/summary');

        // Act
        try {
            $this->router()->resolve($request);
            $this->fail('Expected MethodNotAllowed exception');
        } catch (MethodNotAllowed $e) {
            // Assert
            $this->assertSame(['GET'], $e->getAllowedMethods());
        }
    }

    public function testMethodNotAllowedIs405NotGenericHttpException(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/contact/submit');

        // Act
        try {
            $this->router()->resolve($request);
            $this->fail('Expected MethodNotAllowed exception');
        } catch (MethodNotAllowed $e) {
            // Assert — verify it's specifically a 405, not a generic HttpException
            $this->assertSame(StatusCode::MethodNotAllowed, $e->getStatusCode());
        }
    }

    // ---------------------------------------------------------------
    // 404 — neither Query nor Command class exists
    // ---------------------------------------------------------------

    public function testNonExistentPathThrows404(): void
    {
        // Arrange — no fixtures exist for /nowhere/nothing
        $request = $this->stubRequest('GET', '/nowhere/nothing');

        // Act & Assert
        $this->expectException(HttpException::class);
        $this->router()->resolve($request);
    }

    public function testNonExistentPathThrowsNotFoundStatusCode(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/nowhere/nothing');

        // Act
        try {
            $this->router()->resolve($request);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            // Assert
            $this->assertSame(StatusCode::NotFound, $e->getStatusCode());
        }
    }

    public function testNonExistentPathWithCommandMethodThrows404(): void
    {
        // Arrange — no fixtures for /nowhere/nothing in either namespace
        $request = $this->stubRequest('PUT', '/nowhere/nothing');

        // Act
        try {
            $this->router()->resolve($request);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            // Assert
            $this->assertSame(StatusCode::NotFound, $e->getStatusCode());
        }
    }

    // ---------------------------------------------------------------
    // allowedMethods()
    // ---------------------------------------------------------------

    public function testAllowedMethodsForQueryOnlyPath(): void
    {
        // Arrange — Reports\Query\Summary exists, no Command
        $router = $this->router();

        // Act
        $methods = $router->allowedMethods('/reports/summary');

        // Assert
        $this->assertSame(['GET'], $methods);
    }

    public function testAllowedMethodsForCommandOnlyPath(): void
    {
        // Arrange — Contact\Command\Submit exists, no Query
        $router = $this->router();

        // Act
        $methods = $router->allowedMethods('/contact/submit');

        // Assert
        $this->assertSame(['PUT', 'POST', 'PATCH', 'DELETE'], $methods);
    }

    public function testAllowedMethodsForPathWithBothQueryAndCommand(): void
    {
        // Arrange — Shop\Query\Products and Shop\Command\Products both exist
        $router = $this->router();

        // Act
        $methods = $router->allowedMethods('/shop/products');

        // Assert
        $this->assertSame(['GET', 'PUT', 'POST', 'PATCH', 'DELETE'], $methods);
    }

    public function testAllowedMethodsForNonExistentPath(): void
    {
        // Arrange
        $router = $this->router();

        // Act
        $methods = $router->allowedMethods('/nowhere/nothing');

        // Assert
        $this->assertSame([], $methods);
    }

    public function testAllowedMethodsForPage(): void
    {
        // Arrange
        $router = $this->routerWithPages();

        // Act
        $methods = $router->allowedMethods('/');

        // Assert
        $this->assertSame(['GET'], $methods);
    }

    public function testAllowedMethodsStripsExtension(): void
    {
        // Arrange — Reports\Query\Summary exists
        $router = $this->router();

        // Act
        $methods = $router->allowedMethods('/reports/summary.json');

        // Assert
        $this->assertSame(['GET'], $methods);
    }

    // ---------------------------------------------------------------
    // Handler-only routes (no DTO class, only Handler exists)
    // ---------------------------------------------------------------

    public function testHandlerOnlyQueryRouteResolves(): void
    {
        // Arrange — Widgets\Query\List doesn't exist, but Widgets\Query\ListHandler does
        $request = $this->stubRequest('GET', '/widgets/list');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Widgets\\Query\\List', $route->dtoClass);
        $this->assertTrue($route->isQuery());
    }

    public function testHandlerOnlyRouteAllowedMethods(): void
    {
        // Arrange — only ListHandler exists in Query namespace
        $router = $this->router();

        // Act
        $methods = $router->allowedMethods('/widgets/list');

        // Assert
        $this->assertSame(['GET'], $methods);
    }

    public function testHandlerOnlyRoutePutThrows405(): void
    {
        // Arrange — Widgets\Query\ListHandler exists, no Command handler
        $request = $this->stubRequest('PUT', '/widgets/list');

        // Act & Assert
        $this->expectException(MethodNotAllowed::class);
        $this->router()->resolve($request);
    }

    // ---------------------------------------------------------------
    // #[AllowedFormats] — format restriction via DTO attribute
    // ---------------------------------------------------------------

    public function testThrows406WhenFormatNotAllowed(): void
    {
        // Arrange — Shop\Query\Inventory has #[AllowedFormats('json')]
        $request = $this->stubRequest('GET', '/shop/inventory.html');

        // Act & Assert
        $this->expectException(HttpException::class);
        $this->router()->resolve($request);
    }

    public function testAllowsMatchingFormat(): void
    {
        // Arrange — Shop\Query\Inventory has #[AllowedFormats('json')]
        $request = $this->stubRequest('GET', '/shop/inventory.json');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Shop\\Query\\Inventory', $route->dtoClass);
        $this->assertSame('json', $route->format);
    }

    public function testNoAttributeMeansAllFormatsAllowed(): void
    {
        // Arrange — Shop\Query\Products has no #[AllowedFormats]
        $request = $this->stubRequest('GET', '/shop/products.csv');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame(self::ROOT_NS . '\\Shop\\Query\\Products', $route->dtoClass);
        $this->assertSame('csv', $route->format);
    }

    public function testMultipleAllowedFormats(): void
    {
        // Arrange — Shop\Query\Catalog has #[AllowedFormats('json', 'html', 'csv')]
        $router = $this->router();

        // Act
        $json = $router->resolve($this->stubRequest('GET', '/shop/catalog.json'));
        $html = $router->resolve($this->stubRequest('GET', '/shop/catalog.html'));
        $csv = $router->resolve($this->stubRequest('GET', '/shop/catalog.csv'));

        // Assert
        $this->assertSame('json', $json->format);
        $this->assertSame('html', $html->format);
        $this->assertSame('csv', $csv->format);
    }

    public function testMultipleAllowedFormatsRejectsUnlisted(): void
    {
        // Arrange — Shop\Query\Catalog has #[AllowedFormats('json', 'html', 'csv')]
        $request = $this->stubRequest('GET', '/shop/catalog.xml');

        // Act & Assert
        $this->expectException(HttpException::class);
        $this->router()->resolve($request);
    }

    public function test406StatusCodeForDisallowedFormat(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/shop/inventory.html');

        // Act
        try {
            $this->router()->resolve($request);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            // Assert
            $this->assertSame(StatusCode::NotAcceptable, $e->getStatusCode());
        }
    }

    public function test406MessageIncludesClassAndFormats(): void
    {
        // Arrange
        $request = $this->stubRequest('GET', '/shop/inventory.html');

        // Act
        try {
            $this->router()->resolve($request);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            // Assert
            $this->assertStringContainsString('html', $e->getMessage());
            $this->assertStringContainsString('Inventory', $e->getMessage());
            $this->assertStringContainsString('json', $e->getMessage());
        }
    }

    public function testFormatCheckAppliesToPages(): void
    {
        // Arrange — Pages\RestrictedPage has #[AllowedFormats('html')]
        $pages = new PageResolver(namespace: self::PAGES_NS);
        $pages->register('/restricted-page');
        $router = new HttpRouter(new ConventionResolver(self::ROOT_NS), pages: $pages);

        $request = $this->stubRequest('GET', '/restricted-page.json');

        // Act & Assert
        $this->expectException(HttpException::class);
        $router->resolve($request);
    }

    public function testPageWithAllowedFormatSucceeds(): void
    {
        // Arrange — Pages\RestrictedPage has #[AllowedFormats('html')]
        $pages = new PageResolver(namespace: self::PAGES_NS);
        $pages->register('/restricted-page');
        $router = new HttpRouter(new ConventionResolver(self::ROOT_NS), pages: $pages);

        $request = $this->stubRequest('GET', '/restricted-page.html');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame(self::PAGES_NS . '\\RestrictedPage', $route->dtoClass);
        $this->assertSame('html', $route->format);
    }

    public function testDefaultFormatCheckedAgainstAllowedFormats(): void
    {
        // Arrange — Shop\Query\Inventory has #[AllowedFormats('json')],
        // default format is 'json', no extension → should succeed
        $request = $this->stubRequest('GET', '/shop/inventory');

        // Act
        $route = $this->router()->resolve($request);

        // Assert
        $this->assertSame('json', $route->format);
    }

    public function testDefaultFormatRejectedWhenNotAllowed(): void
    {
        // Arrange — Shop\Query\Inventory has #[AllowedFormats('json')],
        // but default format is 'html' → should fail
        $router = new HttpRouter(new ConventionResolver(self::ROOT_NS), defaultFormat: 'html');
        $request = $this->stubRequest('GET', '/shop/inventory');

        // Act & Assert
        $this->expectException(HttpException::class);
        $router->resolve($request);
    }
}
