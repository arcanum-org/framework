<?php

declare(strict_types=1);

namespace Arcanum\Test\Cabinet;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(\Arcanum\Cabinet\PrototypeProvider::class)]
final class PrototypeProviderTest extends TestCase
{
    public function testPrototypeProvider(): void
    {
        // Arrange
        $provider = \Arcanum\Cabinet\PrototypeProvider::fromFactory(fn() => new \stdClass());
        $container = $this->createStub(\Arcanum\Cabinet\Container::class);

        // Act
        $result = $provider($container);

        // Assert
        $this->assertInstanceOf(\stdClass::class, $result);
        $this->assertNotSame($provider($container), $result);
    }
}
