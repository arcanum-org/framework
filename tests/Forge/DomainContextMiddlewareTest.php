<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\DomainContext;
use Arcanum\Forge\DomainContextMiddleware;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(DomainContextMiddleware::class)]
#[UsesClass(DomainContext::class)]
final class DomainContextMiddlewareTest extends TestCase
{
    public function testSetsDomainContextFromDtoNamespace(): void
    {
        // Arrange
        $context = new DomainContext(domainRoot: '/app/Domain');
        $middleware = new DomainContextMiddleware(
            context: $context,
            namespace: 'Arcanum\\Test\\Forge\\Fixture',
        );
        $dto = new Fixture\Shop\Command\PlaceOrder();
        $called = false;

        // Act
        $middleware($dto, function () use (&$called) {
            $called = true;
        });

        // Assert
        $this->assertTrue($called);
        $this->assertSame('Shop', $context->get());
    }

    public function testSetsDomainContextFromNestedNamespace(): void
    {
        // Arrange
        $context = new DomainContext(domainRoot: '/app/Domain');
        $middleware = new DomainContextMiddleware(
            context: $context,
            namespace: 'Arcanum\\Test\\Forge\\Fixture',
        );
        $dto = new Fixture\Admin\Users\Query\ListUsers();

        // Act
        $middleware($dto, function () {
        });

        // Assert
        $this->assertSame('Admin\\Users', $context->get());
    }
}
