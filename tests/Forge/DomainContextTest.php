<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\DomainContext;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DomainContext::class)]
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

    public function testExtractDomainThrowsOnWrongNamespace(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not under the domain namespace');
        DomainContext::extractDomain(
            'Other\\Namespace\\Foo',
            'App\\Domain',
        );
    }

    public function testExtractDomainThrowsOnNoDomainSegment(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no domain segment found');
        DomainContext::extractDomain(
            'App\\Domain\\Command\\PlaceOrder',
            'App\\Domain',
        );
    }
}
