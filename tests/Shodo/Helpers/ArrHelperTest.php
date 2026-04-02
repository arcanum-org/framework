<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Helpers;

use Arcanum\Shodo\Helpers\ArrHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ArrHelper::class)]
final class ArrHelperTest extends TestCase
{
    public function testCountArray(): void
    {
        // Arrange
        $helper = new ArrHelper();

        // Act
        $result = $helper->count([1, 2, 3]);

        // Assert
        $this->assertSame(3, $result);
    }

    public function testCountCountable(): void
    {
        // Arrange
        $helper = new ArrHelper();
        $items = new \ArrayObject([1, 2, 3, 4]);

        // Act
        $result = $helper->count($items);

        // Assert
        $this->assertSame(4, $result);
    }

    public function testCountEmptyArray(): void
    {
        // Arrange
        $helper = new ArrHelper();

        // Act
        $result = $helper->count([]);

        // Assert
        $this->assertSame(0, $result);
    }

    public function testJoinWithSeparator(): void
    {
        // Arrange
        $helper = new ArrHelper();

        // Act
        $result = $helper->join(['a', 'b', 'c'], ', ');

        // Assert
        $this->assertSame('a, b, c', $result);
    }

    public function testJoinEmptyArray(): void
    {
        // Arrange
        $helper = new ArrHelper();

        // Act
        $result = $helper->join([], ', ');

        // Assert
        $this->assertSame('', $result);
    }

    public function testFirstOfPopulatedArray(): void
    {
        // Arrange
        $helper = new ArrHelper();

        // Act
        $result = $helper->first(['a', 'b', 'c']);

        // Assert
        $this->assertSame('a', $result);
    }

    public function testFirstOfEmptyArrayReturnsNull(): void
    {
        // Arrange
        $helper = new ArrHelper();

        // Act
        $result = $helper->first([]);

        // Assert
        $this->assertNull($result);
    }

    public function testLastOfPopulatedArray(): void
    {
        // Arrange
        $helper = new ArrHelper();

        // Act
        $result = $helper->last(['a', 'b', 'c']);

        // Assert
        $this->assertSame('c', $result);
    }

    public function testLastOfEmptyArrayReturnsNull(): void
    {
        // Arrange
        $helper = new ArrHelper();

        // Act
        $result = $helper->last([]);

        // Assert
        $this->assertNull($result);
    }
}
