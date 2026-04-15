<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas;

use Arcanum\Atlas\RouteMap;
use Arcanum\Atlas\UnresolvableRoute;
use Arcanum\Atlas\UrlResolver;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(UrlResolver::class)]
#[UsesClass(RouteMap::class)]
#[UsesClass(Strings::class)]
final class UrlResolverTest extends TestCase
{
    public function testConventionQuery(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');

        // Act
        $result = $resolver->resolve('App\\Domain\\Shop\\Query\\Products');

        // Assert
        $this->assertSame('/shop/products', $result);
    }

    public function testConventionCommand(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');

        // Act
        $result = $resolver->resolve('App\\Domain\\Contact\\Command\\Submit');

        // Assert
        $this->assertSame('/contact/submit', $result);
    }

    public function testRootLevelQuery(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');

        // Act
        $result = $resolver->resolve('App\\Domain\\Query\\Health');

        // Assert
        $this->assertSame('/health', $result);
    }

    public function testRootLevelCommand(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');

        // Act
        $result = $resolver->resolve('App\\Domain\\Command\\Reset');

        // Assert
        $this->assertSame('/reset', $result);
    }

    public function testDeepDomainQuery(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');

        // Act
        $result = $resolver->resolve('App\\Domain\\Shop\\Query\\Electronics\\Products');

        // Assert
        $this->assertSame('/shop/electronics/products', $result);
    }

    public function testKebabCaseConversion(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');

        // Act
        $result = $resolver->resolve('App\\Domain\\Shop\\Query\\ProductsFeatured');

        // Assert
        $this->assertSame('/shop/products-featured', $result);
    }

    public function testCustomRouteOverridesConvention(): void
    {
        // Arrange
        $routeMap = new RouteMap();
        $routeMap->register('/api/health', 'App\\Domain\\Query\\Health');
        $resolver = new UrlResolver('App\\Domain', routeMap: $routeMap);

        // Act
        $result = $resolver->resolve('App\\Domain\\Query\\Health');

        // Assert
        $this->assertSame('/api/health', $result);
    }

    public function testFallsBackToConventionWhenNotInRouteMap(): void
    {
        // Arrange
        $routeMap = new RouteMap();
        $routeMap->register('/api/health', 'App\\Domain\\Query\\Health');
        $resolver = new UrlResolver('App\\Domain', routeMap: $routeMap);

        // Act
        $result = $resolver->resolve('App\\Domain\\Shop\\Query\\Products');

        // Assert
        $this->assertSame('/shop/products', $result);
    }

    public function testPagesNamespace(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain', pagesNamespace: 'App\\Pages');

        // Act
        $result = $resolver->resolve('App\\Pages\\Docs\\GettingStarted');

        // Assert
        $this->assertSame('/docs/getting-started', $result);
    }

    public function testPagesRootLevel(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain', pagesNamespace: 'App\\Pages');

        // Act
        $result = $resolver->resolve('App\\Pages\\Index');

        // Assert
        $this->assertSame('/index', $result);
    }

    public function testCollapsesPathWhenDomainMatchesClassName(): void
    {
        // Arrange — TaskLists\Query\TaskLists should produce /task-lists, not /task-lists/task-lists
        $resolver = new UrlResolver('App\\Domain');

        // Act
        $result = $resolver->resolve('App\\Domain\\TaskLists\\Query\\TaskLists');

        // Assert
        $this->assertSame('/task-lists', $result);
    }

    public function testCollapsesPathInDeepNesting(): void
    {
        // Arrange — Shop\Products\Query\Products should produce /shop/products
        $resolver = new UrlResolver('App\\Domain');

        // Act
        $result = $resolver->resolve('App\\Domain\\Shop\\Products\\Query\\Products');

        // Assert
        $this->assertSame('/shop/products', $result);
    }

    public function testNoCollapseWhenClassNameDiffersFromDomain(): void
    {
        // Arrange — Shop\Query\FeaturedProducts should stay /shop/featured-products
        $resolver = new UrlResolver('App\\Domain');

        // Act
        $result = $resolver->resolve('App\\Domain\\Shop\\Query\\FeaturedProducts');

        // Assert
        $this->assertSame('/shop/featured-products', $result);
    }

    public function testCollapseEdgeCaseDtoNameMatchesDomainTwoLevelsUp(): void
    {
        // Arrange — Shop\Query\Shop → DTO name matches top-level domain, should collapse to /shop
        $resolver = new UrlResolver('App\\Domain');

        // Act
        $result = $resolver->resolve('App\\Domain\\Shop\\Query\\Shop');

        // Assert
        $this->assertSame('/shop', $result);
    }

    public function testCollapsesCommandPath(): void
    {
        // Arrange — Tasks\Command\Tasks should produce /tasks, not /tasks/tasks
        $resolver = new UrlResolver('App\\Domain');

        // Act
        $result = $resolver->resolve('App\\Domain\\Tasks\\Command\\Tasks');

        // Assert
        $this->assertSame('/tasks', $result);
    }

    public function testThrowsForUnknownNamespace(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $resolver->resolve('Other\\Namespace\\Foo');
    }

    public function testThrowsForMissingTypeNamespace(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');

        // Act & Assert
        $this->expectException(UnresolvableRoute::class);
        $this->expectExceptionMessage('no Query or Command namespace found');
        $resolver->resolve('App\\Domain\\Shop\\Products');
    }
}
