<?php

declare(strict_types=1);

namespace Arcanum\Test\Atlas;

use Arcanum\Atlas\LocationResolver;
use Arcanum\Atlas\UnresolvableRoute;
use Arcanum\Atlas\UrlResolver;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(LocationResolver::class)]
#[UsesClass(UrlResolver::class)]
#[UsesClass(UnresolvableRoute::class)]
#[UsesClass(Strings::class)]
final class LocationResolverTest extends TestCase
{
    private function resolver(string $baseUrl = ''): LocationResolver
    {
        return new LocationResolver(
            new UrlResolver('Arcanum\\Test\\Fixture'),
            $baseUrl,
        );
    }

    public function testResolvesQueryDtoWithProperties(): void
    {
        // Arrange
        $dto = new \Arcanum\Test\Fixture\Shop\Query\Inventory(id: 'abc123');
        $resolver = $this->resolver('https://api.example.com');

        // Act
        $url = $resolver->resolve($dto);

        // Assert
        $this->assertSame('https://api.example.com/shop/inventory?id=abc123', $url);
    }

    public function testResolvesQueryDtoWithoutProperties(): void
    {
        // Arrange — Products has no constructor params
        $dto = new \Arcanum\Test\Fixture\Shop\Query\Products();
        $resolver = $this->resolver('https://api.example.com');

        // Act
        $url = $resolver->resolve($dto);

        // Assert
        $this->assertSame('https://api.example.com/shop/products', $url);
    }

    public function testReturnsNullForUnresolvableClass(): void
    {
        // Arrange — stdClass is not in any routable namespace
        $dto = new \stdClass();
        $resolver = $this->resolver();

        // Act
        $url = $resolver->resolve($dto);

        // Assert
        $this->assertNull($url);
    }

    public function testBaseUrlPrepended(): void
    {
        // Arrange
        $dto = new \Arcanum\Test\Fixture\Shop\Query\Products();
        $resolver = $this->resolver('https://myapp.com');

        // Act
        $url = $resolver->resolve($dto);

        // Assert
        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://myapp.com/', $url);
    }

    public function testEmptyBaseUrlProducesRelativeUrl(): void
    {
        // Arrange
        $dto = new \Arcanum\Test\Fixture\Shop\Query\Products();
        $resolver = $this->resolver();

        // Act
        $url = $resolver->resolve($dto);

        // Assert
        $this->assertSame('/shop/products', $url);
    }

    public function testTrailingSlashOnBaseUrlNormalized(): void
    {
        // Arrange
        $dto = new \Arcanum\Test\Fixture\Shop\Query\Products();
        $resolver = $this->resolver('https://api.example.com/');

        // Act
        $url = $resolver->resolve($dto);

        // Assert
        $this->assertSame('https://api.example.com/shop/products', $url);
    }

    public function testMultiplePropertiesBuildQueryString(): void
    {
        // Arrange
        $dto = new \Arcanum\Test\Fixture\Shop\Query\Catalog(
            category: 'electronics',
            page: 2,
        );
        $resolver = $this->resolver('https://api.example.com');

        // Act
        $url = $resolver->resolve($dto);

        // Assert
        $this->assertSame(
            'https://api.example.com/shop/catalog?category=electronics&page=2',
            $url,
        );
    }
}
