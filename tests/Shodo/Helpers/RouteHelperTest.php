<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Helpers;

use Arcanum\Atlas\UrlResolver;
use Arcanum\Shodo\Helpers\RouteHelper;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(RouteHelper::class)]
#[UsesClass(UrlResolver::class)]
#[UsesClass(Strings::class)]
final class RouteHelperTest extends TestCase
{
    public function testUrlResolvesAndPrependsBase(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');
        $helper = new RouteHelper($resolver, 'https://example.com');

        // Act
        $result = $helper->url('App\\Domain\\Query\\Health');

        // Assert
        $this->assertSame('https://example.com/health', $result);
    }

    public function testUrlWithEmptyBaseUrl(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');
        $helper = new RouteHelper($resolver);

        // Act
        $result = $helper->url('App\\Domain\\Shop\\Query\\Products');

        // Assert
        $this->assertSame('/shop/products', $result);
    }

    public function testUrlWithTrailingSlashBaseUrl(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');
        $helper = new RouteHelper($resolver, 'https://example.com/');

        // Act
        $result = $helper->url('App\\Domain\\Query\\Health');

        // Assert
        $this->assertSame('https://example.com/health', $result);
    }

    public function testAssetPrependsBase(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');
        $helper = new RouteHelper($resolver, 'https://example.com');

        // Act
        $result = $helper->asset('css/app.css');

        // Assert
        $this->assertSame('https://example.com/css/app.css', $result);
    }

    public function testAssetWithLeadingSlash(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');
        $helper = new RouteHelper($resolver, 'https://example.com');

        // Act
        $result = $helper->asset('/css/app.css');

        // Assert
        $this->assertSame('https://example.com/css/app.css', $result);
    }

    public function testAssetWithTrailingSlashBaseUrl(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');
        $helper = new RouteHelper($resolver, 'https://example.com/');

        // Act
        $result = $helper->asset('css/app.css');

        // Assert
        $this->assertSame('https://example.com/css/app.css', $result);
    }

    public function testAssetWithEmptyBaseUrl(): void
    {
        // Arrange
        $resolver = new UrlResolver('App\\Domain');
        $helper = new RouteHelper($resolver);

        // Act
        $result = $helper->asset('css/app.css');

        // Assert
        $this->assertSame('/css/app.css', $result);
    }
}
