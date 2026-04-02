<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Helper;

use Arcanum\Shodo\Helper\HelperDiscovery;
use Arcanum\Shodo\Helper\HelperRegistry;
use Arcanum\Shodo\Helper\HelperResolver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Container\ContainerInterface;

#[CoversClass(HelperResolver::class)]
#[UsesClass(HelperRegistry::class)]
final class HelperResolverTest extends TestCase
{
    public function testReturnsGlobalHelpersForAnyDto(): void
    {
        // Arrange
        $format = new \stdClass();
        $registry = new HelperRegistry();
        $registry->register('Format', $format);
        $resolver = new HelperResolver($registry);

        // Act
        $helpers = $resolver->for('App\\Domain\\Shop\\Query\\Products');

        // Assert
        $this->assertSame(['Format' => $format], $helpers);
    }

    public function testReturnsGlobalHelpersWhenNoDiscovery(): void
    {
        // Arrange
        $format = new \stdClass();
        $registry = new HelperRegistry();
        $registry->register('Format', $format);
        $resolver = new HelperResolver($registry);

        // Act
        $shopHelpers = $resolver->for('App\\Domain\\Shop\\Query\\Products');
        $authHelpers = $resolver->for('App\\Domain\\Auth\\Query\\Whoami');

        // Assert — both get the same global helpers
        $this->assertSame($shopHelpers, $authHelpers);
    }

    public function testDomainHelpersAddedForMatchingDto(): void
    {
        // Arrange
        $format = new \stdClass();
        $cart = new \stdClass();
        $registry = new HelperRegistry();
        $registry->register('Format', $format);

        $discovery = $this->createStub(HelperDiscovery::class);
        $discovery->method('discover')->willReturn([
            'App\\Domain\\Shop' => ['Cart' => 'App\\Domain\\Shop\\CartHelper'],
        ]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            fn(string $id) => match ($id) {
                'App\\Domain\\Shop\\CartHelper' => $cart,
                default => throw new \RuntimeException("Unexpected: $id"),
            },
        );

        $resolver = new HelperResolver($registry, $discovery, $container);

        // Act
        $helpers = $resolver->for('App\\Domain\\Shop\\Query\\Products');

        // Assert
        $this->assertSame($format, $helpers['Format']);
        $this->assertSame($cart, $helpers['Cart']);
    }

    public function testDomainHelpersNotIncludedForUnrelatedDto(): void
    {
        // Arrange
        $format = new \stdClass();
        $registry = new HelperRegistry();
        $registry->register('Format', $format);

        $discovery = $this->createStub(HelperDiscovery::class);
        $discovery->method('discover')->willReturn([
            'App\\Domain\\Shop' => ['Cart' => 'App\\Domain\\Shop\\CartHelper'],
        ]);

        $container = $this->createStub(ContainerInterface::class);
        $resolver = new HelperResolver($registry, $discovery, $container);

        // Act
        $helpers = $resolver->for('App\\Domain\\Auth\\Query\\Whoami');

        // Assert — only global, no Cart
        $this->assertSame(['Format' => $format], $helpers);
    }

    public function testDomainAliasOverridesGlobal(): void
    {
        // Arrange
        $globalFormat = new \stdClass();
        $shopFormat = new \stdClass();
        $registry = new HelperRegistry();
        $registry->register('Format', $globalFormat);

        $discovery = $this->createStub(HelperDiscovery::class);
        $discovery->method('discover')->willReturn([
            'App\\Domain\\Shop' => ['Format' => 'App\\Domain\\Shop\\ShopFormatHelper'],
        ]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturn($shopFormat);

        $resolver = new HelperResolver($registry, $discovery, $container);

        // Act
        $helpers = $resolver->for('App\\Domain\\Shop\\Query\\Products');

        // Assert — domain Format overrides global
        $this->assertSame($shopFormat, $helpers['Format']);
    }

    public function testDeeperDomainOverridesShallower(): void
    {
        // Arrange
        $shopCart = new \stdClass();
        $checkoutCart = new \stdClass();
        $registry = new HelperRegistry();

        $discovery = $this->createStub(HelperDiscovery::class);
        $discovery->method('discover')->willReturn([
            'App\\Domain\\Shop' => ['Cart' => 'ShopCartHelper'],
            'App\\Domain\\Shop\\Checkout' => ['Cart' => 'CheckoutCartHelper'],
        ]);

        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            fn(string $id) => match ($id) {
                'ShopCartHelper' => $shopCart,
                'CheckoutCartHelper' => $checkoutCart,
                default => throw new \RuntimeException("Unexpected: $id"),
            },
        );

        $resolver = new HelperResolver($registry, $discovery, $container);

        // Act
        $helpers = $resolver->for('App\\Domain\\Shop\\Checkout\\Command\\PlaceOrder');

        // Assert — deeper Checkout overrides Shop
        $this->assertSame($checkoutCart, $helpers['Cart']);
    }

    public function testResultsAreCachedPerDtoClass(): void
    {
        // Arrange
        $discovery = $this->createMock(HelperDiscovery::class);
        $discovery->expects($this->once())->method('discover')->willReturn([]);
        $container = $this->createStub(ContainerInterface::class);
        $registry = new HelperRegistry();
        $resolver = new HelperResolver($registry, $discovery, $container);

        // Act — call twice for same DTO
        $resolver->for('App\\Domain\\Query\\Health');
        $resolver->for('App\\Domain\\Query\\Health');

        // Assert — discover() only called once (verified by expects)
    }
}
