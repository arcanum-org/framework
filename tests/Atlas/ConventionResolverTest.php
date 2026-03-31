<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas;

use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\Route;
use Arcanum\Atlas\UnresolvableRoute;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ConventionResolver::class)]
#[UsesClass(Route::class)]
#[UsesClass(Strings::class)]
final class ConventionResolverTest extends TestCase
{
    // ---------------------------------------------------------------
    // Kebab-case to PascalCase
    // ---------------------------------------------------------------

    public function testKebabCaseSegmentConvertedToPascalCase(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/submit-payment', 'PUT');

        // Assert
        $this->assertSame('App\\Command\\SubmitPayment', $route->dtoClass);
    }

    public function testMultipleKebabSegmentsAllConvertedToPascalCase(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/my-store/submit-payment', 'PUT');

        // Assert
        $this->assertSame('App\\MyStore\\Command\\SubmitPayment', $route->dtoClass);
    }

    public function testAlreadyPascalCaseSegmentsAreUnchanged(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/Store/Payment', 'GET');

        // Assert
        $this->assertSame('App\\Store\\Query\\Payment', $route->dtoClass);
    }

    // ---------------------------------------------------------------
    // Single-segment paths
    // ---------------------------------------------------------------

    public function testSingleSegmentGetResolvesToQuery(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/dashboard', 'GET');

        // Assert
        $this->assertSame('App\\Query\\Dashboard', $route->dtoClass);
    }

    public function testSingleSegmentPutResolvesToCommand(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/submit', 'PUT');

        // Assert
        $this->assertSame('App\\Command\\Submit', $route->dtoClass);
    }

    // ---------------------------------------------------------------
    // Multi-segment paths
    // ---------------------------------------------------------------

    public function testTwoSegmentGetResolvesToQuery(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/shop/new-products', 'GET');

        // Assert
        $this->assertSame('App\\Shop\\Query\\NewProducts', $route->dtoClass);
    }

    public function testThreeSegmentGetResolvesToQueryWithNestedNamespace(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/catalog/products/featured', 'GET');

        // Assert
        $this->assertSame('App\\Catalog\\Query\\Products\\Featured', $route->dtoClass);
    }

    public function testFourSegmentGetResolvesToDeeplyNestedQuery(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/admin/catalog/products/featured', 'GET');

        // Assert
        $this->assertSame('App\\Admin\\Query\\Catalog\\Products\\Featured', $route->dtoClass);
    }

    public function testTwoSegmentPutResolvesToCommand(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/checkout/submit-payment', 'PUT');

        // Assert
        $this->assertSame('App\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
    }

    // ---------------------------------------------------------------
    // GET → Query namespace insertion
    // ---------------------------------------------------------------

    public function testGetInsertsQueryNamespace(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/orders/recent', 'GET');

        // Assert
        $this->assertSame('App\\Orders\\Query\\Recent', $route->dtoClass);
        $this->assertTrue($route->isQuery());
        $this->assertFalse($route->isCommand());
    }

    public function testGetHasEmptyHandlerPrefix(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/orders/recent', 'GET');

        // Assert
        $this->assertSame('', $route->handlerPrefix);
    }

    // ---------------------------------------------------------------
    // PUT → Command namespace insertion
    // ---------------------------------------------------------------

    public function testPutInsertsCommandNamespace(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/orders/place', 'PUT');

        // Assert
        $this->assertSame('App\\Orders\\Command\\Place', $route->dtoClass);
        $this->assertTrue($route->isCommand());
        $this->assertFalse($route->isQuery());
    }

    public function testPutHasEmptyHandlerPrefix(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/orders/place', 'PUT');

        // Assert
        $this->assertSame('', $route->handlerPrefix);
    }

    // ---------------------------------------------------------------
    // POST/PATCH/DELETE → Command namespace with handler prefix
    // ---------------------------------------------------------------

    public function testPostInsertsCommandNamespaceWithPostPrefix(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/checkout/submit-payment', 'POST');

        // Assert
        $this->assertSame('App\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('Post', $route->handlerPrefix);
        $this->assertTrue($route->isCommand());
    }

    public function testPatchInsertsCommandNamespaceWithPatchPrefix(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/checkout/submit-payment', 'PATCH');

        // Assert
        $this->assertSame('App\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('Patch', $route->handlerPrefix);
        $this->assertTrue($route->isCommand());
    }

    public function testDeleteInsertsCommandNamespaceWithDeletePrefix(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/checkout/submit-payment', 'DELETE');

        // Assert
        $this->assertSame('App\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('Delete', $route->handlerPrefix);
        $this->assertTrue($route->isCommand());
    }

    public function testAllMutatingMethodsShareSameDtoClass(): void
    {
        // Arrange
        $resolver = new ConventionResolver();
        $path = '/store/submit';

        // Act
        $put = $resolver->resolve($path, 'PUT');
        $post = $resolver->resolve($path, 'POST');
        $patch = $resolver->resolve($path, 'PATCH');
        $delete = $resolver->resolve($path, 'DELETE');

        // Assert
        $this->assertSame($put->dtoClass, $post->dtoClass);
        $this->assertSame($put->dtoClass, $patch->dtoClass);
        $this->assertSame($put->dtoClass, $delete->dtoClass);
    }

    // ---------------------------------------------------------------
    // Configurable root namespace
    // ---------------------------------------------------------------

    public function testCustomRootNamespace(): void
    {
        // Arrange
        $resolver = new ConventionResolver(rootNamespace: 'BoilerRoom');

        // Act
        $route = $resolver->resolve('/shop/tickers', 'GET');

        // Assert
        $this->assertSame('BoilerRoom\\Shop\\Query\\Tickers', $route->dtoClass);
    }

    public function testCustomRootNamespaceForCommand(): void
    {
        // Arrange
        $resolver = new ConventionResolver(rootNamespace: 'MyCompany\\Platform');

        // Act
        $route = $resolver->resolve('/billing/charge', 'PUT');

        // Assert
        $this->assertSame('MyCompany\\Platform\\Billing\\Command\\Charge', $route->dtoClass);
    }

    public function testDefaultRootNamespaceIsApp(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/dashboard', 'GET');

        // Assert
        $this->assertStringStartsWith('App\\', $route->dtoClass);
    }

    // ---------------------------------------------------------------
    // Format and path parameters passthrough
    // ---------------------------------------------------------------

    public function testFormatIsPassedThrough(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/shop/products', 'GET', format: 'csv');

        // Assert
        $this->assertSame('csv', $route->format);
    }

    public function testDefaultFormatIsJson(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/shop/products', 'GET');

        // Assert
        $this->assertSame('json', $route->format);
    }

    // ---------------------------------------------------------------
    // Edge cases
    // ---------------------------------------------------------------

    public function testLeadingAndTrailingSlashesAreStripped(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('///shop/products///', 'GET');

        // Assert
        $this->assertSame('App\\Shop\\Query\\Products', $route->dtoClass);
    }

    public function testEmptyPathThrowsUnresolvableRoute(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $resolver->resolve('/', 'GET');
    }

    public function testEmptyStringPathThrowsUnresolvableRoute(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $resolver->resolve('', 'GET');
    }

    public function testMethodIsCaseInsensitive(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/orders/place', 'put');

        // Assert
        $this->assertSame('App\\Orders\\Command\\Place', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
    }

    public function testPostMethodIsCaseInsensitive(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/orders/place', 'post');

        // Assert
        $this->assertSame('Post', $route->handlerPrefix);
    }

    // ---------------------------------------------------------------
    // Full convention examples from the plan
    // ---------------------------------------------------------------

    public function testPlanExampleGetCatalogProductsFeatured(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/catalog/products/featured', 'GET', format: 'json');

        // Assert
        $this->assertSame('App\\Catalog\\Query\\Products\\Featured', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
        $this->assertSame('json', $route->format);
    }

    public function testPlanExamplePutCheckoutSubmitPayment(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/checkout/submit-payment', 'PUT');

        // Assert
        $this->assertSame('App\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
    }

    public function testPlanExamplePostCheckoutSubmitPayment(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/checkout/submit-payment', 'POST');

        // Assert
        $this->assertSame('App\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('Post', $route->handlerPrefix);
    }

    public function testPlanExampleDeleteCheckoutSubmitPayment(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolve('/checkout/submit-payment', 'DELETE');

        // Assert
        $this->assertSame('App\\Checkout\\Command\\SubmitPayment', $route->dtoClass);
        $this->assertSame('Delete', $route->handlerPrefix);
    }

    public function testPlanExampleCustomNamespace(): void
    {
        // Arrange
        $resolver = new ConventionResolver(rootNamespace: 'BoilerRoom');

        // Act
        $route = $resolver->resolve('/shop/tickers', 'GET');

        // Assert
        $this->assertSame('BoilerRoom\\Shop\\Query\\Tickers', $route->dtoClass);
    }

    // ---------------------------------------------------------------
    // resolveByType — transport-agnostic core
    // ---------------------------------------------------------------

    public function testResolveByTypeWithQueryNamespace(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolveByType('/health', 'Query');

        // Assert
        $this->assertSame('App\\Query\\Health', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
        $this->assertTrue($route->isQuery());
    }

    public function testResolveByTypeWithCommandNamespace(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolveByType('/contact/submit', 'Command');

        // Assert
        $this->assertSame('App\\Contact\\Command\\Submit', $route->dtoClass);
        $this->assertSame('', $route->handlerPrefix);
        $this->assertTrue($route->isCommand());
    }

    public function testResolveByTypeWithHandlerPrefix(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolveByType('/orders/place', 'Command', handlerPrefix: 'Post');

        // Assert
        $this->assertSame('App\\Orders\\Command\\Place', $route->dtoClass);
        $this->assertSame('Post', $route->handlerPrefix);
    }

    public function testResolveByTypeWithFormat(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolveByType('/users/find', 'Query', format: 'csv');

        // Assert
        $this->assertSame('App\\Users\\Query\\Find', $route->dtoClass);
        $this->assertSame('csv', $route->format);
    }

    public function testResolveByTypeWithCustomRootNamespace(): void
    {
        // Arrange
        $resolver = new ConventionResolver(rootNamespace: 'MyApp');

        // Act
        $route = $resolver->resolveByType('/billing/charge', 'Command');

        // Assert
        $this->assertSame('MyApp\\Billing\\Command\\Charge', $route->dtoClass);
    }

    public function testResolveByTypeWithDeeplyNestedPath(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolveByType('/admin/catalog/products/featured', 'Query');

        // Assert
        $this->assertSame('App\\Admin\\Query\\Catalog\\Products\\Featured', $route->dtoClass);
    }

    public function testResolveByTypeThrowsForEmptyPath(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $resolver->resolveByType('', 'Query');
    }

    public function testResolveByTypeDefaultFormatIsJson(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $route = $resolver->resolveByType('/health', 'Query');

        // Assert
        $this->assertSame('json', $route->format);
    }

    public function testResolveByTypeProducesSameResultAsResolveForGet(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $viaMethod = $resolver->resolve('/orders/recent', 'GET', 'html');
        $viaType = $resolver->resolveByType('/orders/recent', 'Query', format: 'html');

        // Assert
        $this->assertSame($viaMethod->dtoClass, $viaType->dtoClass);
        $this->assertSame($viaMethod->handlerPrefix, $viaType->handlerPrefix);
        $this->assertSame($viaMethod->format, $viaType->format);
    }

    public function testResolveByTypeProducesSameResultAsResolveForPost(): void
    {
        // Arrange
        $resolver = new ConventionResolver();

        // Act
        $viaMethod = $resolver->resolve('/orders/place', 'POST');
        $viaType = $resolver->resolveByType('/orders/place', 'Command', handlerPrefix: 'Post');

        // Assert
        $this->assertSame($viaMethod->dtoClass, $viaType->dtoClass);
        $this->assertSame($viaMethod->handlerPrefix, $viaType->handlerPrefix);
    }
}
