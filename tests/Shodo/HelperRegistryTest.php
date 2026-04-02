<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Shodo\HelperRegistry;
use Arcanum\Shodo\UnknownHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(HelperRegistry::class)]
#[UsesClass(UnknownHelper::class)]
final class HelperRegistryTest extends TestCase
{
    public function testRegisterAndRetrieveHelper(): void
    {
        // Arrange
        $helper = new \stdClass();
        $registry = new HelperRegistry();
        $registry->register('Format', $helper);

        // Act
        $result = $registry->get('Format');

        // Assert
        $this->assertSame($helper, $result);
    }

    public function testHasReturnsTrueForRegisteredAlias(): void
    {
        // Arrange
        $registry = new HelperRegistry();
        $registry->register('Route', new \stdClass());

        // Act & Assert
        $this->assertTrue($registry->has('Route'));
    }

    public function testHasReturnsFalseForUnregisteredAlias(): void
    {
        // Arrange
        $registry = new HelperRegistry();

        // Act & Assert
        $this->assertFalse($registry->has('Route'));
    }

    public function testAllReturnsRegisteredHelpers(): void
    {
        // Arrange
        $format = new \stdClass();
        $route = new \stdClass();
        $registry = new HelperRegistry();
        $registry->register('Format', $format);
        $registry->register('Route', $route);

        // Act
        $all = $registry->all();

        // Assert
        $this->assertSame(['Format' => $format, 'Route' => $route], $all);
    }

    public function testAllReturnsEmptyArrayWhenNothingRegistered(): void
    {
        // Arrange
        $registry = new HelperRegistry();

        // Act & Assert
        $this->assertSame([], $registry->all());
    }

    public function testGetThrowsUnknownHelperForUnregisteredAlias(): void
    {
        // Arrange
        $registry = new HelperRegistry();
        $registry->register('Format', new \stdClass());

        // Act & Assert
        $this->expectException(UnknownHelper::class);
        $this->expectExceptionMessage('Template helper "Route" is not registered. Registered helpers: Format.');
        $registry->get('Route');
    }

    public function testGetThrowsUnknownHelperWithNoRegisteredHelpers(): void
    {
        // Arrange
        $registry = new HelperRegistry();

        // Act & Assert
        $this->expectException(UnknownHelper::class);
        $this->expectExceptionMessage('Template helper "Route" is not registered.');
        $registry->get('Route');
    }

    public function testRegisterSameAliasOverwritesPrevious(): void
    {
        // Arrange
        $first = new \stdClass();
        $second = new \stdClass();
        $registry = new HelperRegistry();
        $registry->register('Format', $first);
        $registry->register('Format', $second);

        // Act
        $result = $registry->get('Format');

        // Assert
        $this->assertSame($second, $result);
    }
}
