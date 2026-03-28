<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas;

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
    private function stubRequest(string $method, string $path): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn($path);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);

        return $request;
    }

    // ---------------------------------------------------------------
    // Basic resolution through to ConventionResolver
    // ---------------------------------------------------------------

    public function testResolvesGetRequestToQuery(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('GET', '/shop/new-products');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Shop\\Query\\NewProducts', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
        $this->assertTrue($route->isQuery());
    }

    public function testResolvesPutRequestToCommand(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('PUT', '/checkout/submit-payment');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
        $this->assertTrue($route->isCommand());
    }

    public function testResolvesPostRequestWithPrefix(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('POST', '/checkout/submit-payment');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('Post', $route->handlerPrefix);
    }

    public function testResolvesDeleteRequestWithPrefix(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('DELETE', '/orders/cancel');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Orders\\Command\\Cancel', $route->dtoClass);
        $this->assertSame('Delete', $route->handlerPrefix);
    }

    public function testResolvesPatchRequestWithPrefix(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('PATCH', '/orders/update-address');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Orders\\Command\\UpdateAddress', $route->dtoClass);
        $this->assertSame('Patch', $route->handlerPrefix);
    }

    // ---------------------------------------------------------------
    // Extension parsing — same handler regardless of extension
    // ---------------------------------------------------------------

    public function testJsonExtensionStrippedAndFormatSet(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('GET', '/shop/new-products.json');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Shop\\Query\\NewProducts', $route->dtoClass);
        $this->assertSame('json', $route->format);
    }

    public function testHtmlExtensionStrippedAndFormatSet(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('GET', '/shop/new-products.html');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Shop\\Query\\NewProducts', $route->dtoClass);
        $this->assertSame('html', $route->format);
    }

    public function testCsvExtensionStrippedAndFormatSet(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('GET', '/shop/new-products.csv');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Shop\\Query\\NewProducts', $route->dtoClass);
        $this->assertSame('csv', $route->format);
    }

    public function testSameHandlerResolvedRegardlessOfExtension(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);

        // Act
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
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('GET', '/catalog/products.csv');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('csv', $route->format);
    }

    public function testExtensionIsLowercased(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('GET', '/shop/products.JSON');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('json', $route->format);
    }

    // ---------------------------------------------------------------
    // Missing extension — fallback format
    // ---------------------------------------------------------------

    public function testMissingExtensionUsesDefaultFormat(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('GET', '/shop/products');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('json', $route->format);
    }

    public function testDefaultFormatIsConfigurable(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), defaultFormat: 'html', validateClasses: false);
        $request = $this->stubRequest('GET', '/shop/products');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('html', $route->format);
    }

    public function testConfiguredDefaultDoesNotOverrideExplicitExtension(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), defaultFormat: 'html', validateClasses: false);
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
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('GET', '/shop/products/');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Shop\\Query\\Products', $route->dtoClass);
        $this->assertSame('json', $route->format);
    }

    public function testExtensionOnlyAppliedToLastSegment(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('GET', '/my.store/products.html');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('html', $route->format);
        // my.store segment is not split by the dot — only the last segment's extension is parsed
        $this->assertSame('App\\My.store\\Query\\Products', $route->dtoClass);
    }

    public function testDotfileSegmentIsNotTreatedAsExtension(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('GET', '/shop/.hidden');

        // Act
        $route = $router->resolve($request);

        // Assert — leading dot means no extension, '.hidden' is the segment name
        $this->assertSame('json', $route->format);
    }

    // ---------------------------------------------------------------
    // Non-ServerRequestInterface input
    // ---------------------------------------------------------------

    public function testNonServerRequestThrowsUnresolvableRoute(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $this->expectExceptionMessage('HttpRouter expects a ServerRequestInterface');
        $router->resolve(new \stdClass());
    }

    // ---------------------------------------------------------------
    // Root path
    // ---------------------------------------------------------------

    public function testRootPathThrowsUnresolvableRoute(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('GET', '/');

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $router->resolve($request);
    }

    // ---------------------------------------------------------------
    // Full plan examples
    // ---------------------------------------------------------------

    public function testPlanExampleGetCatalogFeaturedJson(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('GET', '/catalog/products/featured.json');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Catalog\\Query\\Products\\Featured', $route->dtoClass);
        $this->assertSame('json', $route->format);
        $this->assertSame('', $route->handlerPrefix);
    }

    public function testPlanExamplePutCheckoutSubmitPayment(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('PUT', '/checkout/submit-payment');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
    }

    public function testPlanExampleDeleteCheckoutSubmitPayment(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('DELETE', '/checkout/submit-payment');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('Delete', $route->handlerPrefix);
    }

    // ---------------------------------------------------------------
    // Pages integration
    // ---------------------------------------------------------------

    public function testRootPathResolvesToPageWhenRegistered(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/');
        $router = new HttpRouter(new ConventionResolver(), $pages, validateClasses: false);
        $request = $this->stubRequest('GET', '/');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Pages\\Index', $route->dtoClass);
        $this->assertSame('html', $route->format);
    }

    public function testPageResolvedBeforeConvention(): void
    {
        // Arrange — '/thing' is registered as a page, so it should resolve
        // to App\Pages\Thing, not App\Query\Thing
        $pages = new PageResolver();
        $pages->register('/thing');
        $router = new HttpRouter(new ConventionResolver(), $pages, validateClasses: false);
        $request = $this->stubRequest('GET', '/thing');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Pages\\Thing', $route->dtoClass);
    }

    public function testPageWithExtensionUsesExtensionFormat(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/thing');
        $router = new HttpRouter(new ConventionResolver(), $pages, validateClasses: false);
        $request = $this->stubRequest('GET', '/thing.json');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Pages\\Thing', $route->dtoClass);
        $this->assertSame('json', $route->format);
    }

    public function testPageWithoutExtensionUsesPageDefaultFormat(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/thing');
        $router = new HttpRouter(new ConventionResolver(), $pages, validateClasses: false);
        $request = $this->stubRequest('GET', '/thing');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('html', $route->format);
    }

    public function testUnregisteredPathFallsBackToConvention(): void
    {
        // Arrange — '/shop/products' is not a page, so convention routing kicks in
        $pages = new PageResolver();
        $pages->register('/');
        $router = new HttpRouter(new ConventionResolver(), $pages, validateClasses: false);
        $request = $this->stubRequest('GET', '/shop/products');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Shop\\Query\\Products', $route->dtoClass);
    }

    public function testRootPathWithoutPagesThrowsUnresolvableRoute(): void
    {
        // Arrange — no pages registered, root path has no convention mapping
        $router = new HttpRouter(new ConventionResolver(), validateClasses: false);
        $request = $this->stubRequest('GET', '/');

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $router->resolve($request);
    }

    public function testNestedPageResolvedViaHttpRouter(): void
    {
        // Arrange
        $pages = new PageResolver();
        $pages->register('/docs/getting-started');
        $router = new HttpRouter(new ConventionResolver(), $pages, validateClasses: false);
        $request = $this->stubRequest('GET', '/docs/getting-started');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('App\\Pages\\Docs\\GettingStarted', $route->dtoClass);
    }

    // ---------------------------------------------------------------
    // HTTP method enforcement — pages are GET-only
    // ---------------------------------------------------------------

    public function testPostToPageThrowsMethodNotAllowed(): void
    {
        // Arrange
        $pages = new PageResolver(namespace: 'Arcanum\\Test\\Fixture\\Pages');
        $pages->register('/');
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'), $pages);
        $request = $this->stubRequest('POST', '/');

        // Act & Assert
        $this->expectException(MethodNotAllowed::class);
        $router->resolve($request);
    }

    public function testPutToPageThrowsMethodNotAllowed(): void
    {
        // Arrange
        $pages = new PageResolver(namespace: 'Arcanum\\Test\\Fixture\\Pages');
        $pages->register('/');
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'), $pages);
        $request = $this->stubRequest('PUT', '/');

        // Act & Assert
        $this->expectException(MethodNotAllowed::class);
        $router->resolve($request);
    }

    public function testDeleteToPageThrowsMethodNotAllowed(): void
    {
        // Arrange
        $pages = new PageResolver(namespace: 'Arcanum\\Test\\Fixture\\Pages');
        $pages->register('/');
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'), $pages);
        $request = $this->stubRequest('DELETE', '/');

        // Act & Assert
        $this->expectException(MethodNotAllowed::class);
        $router->resolve($request);
    }

    public function testPageMethodNotAllowedListsGetAsAllowed(): void
    {
        // Arrange
        $pages = new PageResolver(namespace: 'Arcanum\\Test\\Fixture\\Pages');
        $pages->register('/');
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'), $pages);
        $request = $this->stubRequest('POST', '/');

        // Act
        try {
            $router->resolve($request);
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
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'));
        $request = $this->stubRequest('GET', '/contact/submit');

        // Act & Assert
        $this->expectException(MethodNotAllowed::class);
        $router->resolve($request);
    }

    public function testGetToCommandOnlyPathListsCommandMethodsAsAllowed(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'));
        $request = $this->stubRequest('GET', '/contact/submit');

        // Act
        try {
            $router->resolve($request);
            $this->fail('Expected MethodNotAllowed exception');
        } catch (MethodNotAllowed $e) {
            // Assert
            $this->assertSame(['PUT', 'POST', 'PATCH', 'DELETE'], $e->getAllowedMethods());
        }
    }

    public function testGetToPathWithBothQueryAndCommandResolvesQuery(): void
    {
        // Arrange — Shop\Query\Products and Shop\Command\Products both exist
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'));
        $request = $this->stubRequest('GET', '/shop/products');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('Arcanum\\Test\\Fixture\\Shop\\Query\\Products', $route->dtoClass);
        $this->assertTrue($route->isQuery());
    }

    public function testPutToPathWithBothQueryAndCommandResolvesCommand(): void
    {
        // Arrange — Shop\Query\Products and Shop\Command\Products both exist
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'));
        $request = $this->stubRequest('PUT', '/shop/products');

        // Act
        $route = $router->resolve($request);

        // Assert
        $this->assertSame('Arcanum\\Test\\Fixture\\Shop\\Command\\Products', $route->dtoClass);
        $this->assertTrue($route->isCommand());
    }

    // ---------------------------------------------------------------
    // 404 — neither Query nor Command class exists
    // ---------------------------------------------------------------

    public function testNonExistentPathThrows404(): void
    {
        // Arrange — no fixtures exist for /nowhere/nothing
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'));
        $request = $this->stubRequest('GET', '/nowhere/nothing');

        // Act & Assert
        $this->expectException(HttpException::class);
        $router->resolve($request);
    }

    public function testNonExistentPathThrowsNotFoundStatusCode(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'));
        $request = $this->stubRequest('GET', '/nowhere/nothing');

        // Act
        try {
            $router->resolve($request);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            // Assert
            $this->assertSame(StatusCode::NotFound, $e->getStatusCode());
        }
    }

    public function testNonExistentPathWithCommandMethodThrows404(): void
    {
        // Arrange — no fixtures for /nowhere/nothing in either namespace
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'));
        $request = $this->stubRequest('PUT', '/nowhere/nothing');

        // Act
        try {
            $router->resolve($request);
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            // Assert
            $this->assertSame(StatusCode::NotFound, $e->getStatusCode());
        }
    }

    public function testPutToQueryOnlyPathThrowsMethodNotAllowed(): void
    {
        // Arrange — Reports\Query\Summary exists, Reports\Command\Summary does not
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'));
        $request = $this->stubRequest('PUT', '/reports/summary');

        // Act & Assert
        $this->expectException(MethodNotAllowed::class);
        $router->resolve($request);
    }

    public function testPutToQueryOnlyPathListsGetAsAllowed(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'));
        $request = $this->stubRequest('PUT', '/reports/summary');

        // Act
        try {
            $router->resolve($request);
            $this->fail('Expected MethodNotAllowed exception');
        } catch (MethodNotAllowed $e) {
            // Assert
            $this->assertSame(['GET'], $e->getAllowedMethods());
        }
    }

    public function testMethodNotAllowedIs405NotGenericHttpException(): void
    {
        // Arrange
        $router = new HttpRouter(new ConventionResolver('Arcanum\\Test\\Fixture'));
        $request = $this->stubRequest('GET', '/contact/submit');

        // Act
        try {
            $router->resolve($request);
            $this->fail('Expected MethodNotAllowed exception');
        } catch (MethodNotAllowed $e) {
            // Assert — verify it's specifically a 405, not a generic HttpException
            $this->assertSame(StatusCode::MethodNotAllowed, $e->getStatusCode());
        }
    }
}
