<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\DomainContext;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(DomainContext::class)]
#[UsesClass(Strings::class)]
final class DomainContextTest extends TestCase
{
    public function testSetAndGet(): void
    {
        // Arrange
        $context = new DomainContext(domainRoot: '/app/Domain');

        // Act
        $context->set('Shop');

        // Assert
        $this->assertSame('Shop', $context->get());
    }

    public function testGetThrowsWhenNotSet(): void
    {
        // Arrange
        $context = new DomainContext(domainRoot: '/app/Domain');

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No domain context');
        $context->get();
    }

    public function testHas(): void
    {
        // Arrange
        $context = new DomainContext(domainRoot: '/app/Domain');

        // Act & Assert
        $this->assertFalse($context->has());
        $context->set('Shop');
        $this->assertTrue($context->has());
    }

    public function testModelPath(): void
    {
        // Arrange
        $context = new DomainContext(domainRoot: '/app/Domain');
        $context->set('Shop');

        // Act & Assert
        $this->assertSame(
            '/app/Domain' . DIRECTORY_SEPARATOR . 'Shop' . DIRECTORY_SEPARATOR . 'Model',
            $context->modelPath(),
        );
    }

    public function testModelPathWithNestedDomain(): void
    {
        // Arrange
        $context = new DomainContext(domainRoot: '/app/Domain');
        $context->set('Admin\\Users');

        // Act & Assert
        $this->assertSame(
            '/app/Domain' . DIRECTORY_SEPARATOR . 'Admin'
            . DIRECTORY_SEPARATOR . 'Users' . DIRECTORY_SEPARATOR . 'Model',
            $context->modelPath(),
        );
    }

    public function testExtractDomainFromCommand(): void
    {
        $this->assertSame(
            'Shop',
            DomainContext::extractDomain(
                'App\\Domain\\Shop\\Command\\PlaceOrder',
                'App\\Domain',
            ),
        );
    }

    public function testExtractDomainFromQuery(): void
    {
        $this->assertSame(
            'Shop',
            DomainContext::extractDomain(
                'App\\Domain\\Shop\\Query\\Products',
                'App\\Domain',
            ),
        );
    }

    public function testExtractDomainNested(): void
    {
        $this->assertSame(
            'Admin\\Users',
            DomainContext::extractDomain(
                'App\\Domain\\Admin\\Users\\Query\\ListUsers',
                'App\\Domain',
            ),
        );
    }

    public function testExtractDomainReturnsEmptyForWrongNamespace(): void
    {
        // Arrange & Act
        $result = DomainContext::extractDomain(
            'Other\\Namespace\\Foo',
            'App\\Domain',
        );

        // Assert
        $this->assertSame('', $result);
    }

    public function testExtractDomainReturnsEmptyForNoDomainSegment(): void
    {
        // Arrange & Act
        $result = DomainContext::extractDomain(
            'App\\Domain\\Command\\PlaceOrder',
            'App\\Domain',
        );

        // Assert
        $this->assertSame('', $result);
    }

    public function testExtractDomainReturnsEmptyForDomainlessQuery(): void
    {
        // Arrange & Act
        $result = DomainContext::extractDomain(
            'App\\Domain\\Query\\Health',
            'App\\Domain',
        );

        // Assert
        $this->assertSame('', $result);
    }
}
