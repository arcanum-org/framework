<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\Result;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Result::class)]
final class ResultTest extends TestCase
{
    public function testRowsReturnsAllRows(): void
    {
        // Arrange
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        // Act
        $result = new Result(rows: $rows);

        // Assert
        $this->assertSame($rows, $result->rows());
    }

    public function testFirstReturnsFirstRow(): void
    {
        // Arrange
        $rows = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];

        // Act
        $result = new Result(rows: $rows);

        // Assert
        $this->assertSame(['id' => 1, 'name' => 'Alice'], $result->first());
    }

    public function testFirstReturnsNullOnEmpty(): void
    {
        // Arrange
        $result = new Result();

        // Act & Assert
        $this->assertNull($result->first());
    }

    public function testScalarReturnsFirstColumnOfFirstRow(): void
    {
        // Arrange
        $result = new Result(rows: [['count' => 42]]);

        // Act & Assert
        $this->assertSame(42, $result->scalar());
    }

    public function testScalarThrowsOnEmpty(): void
    {
        // Arrange
        $result = new Result();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot get scalar value from an empty result set.');
        $result->scalar();
    }

    public function testIsEmptyWithNoRowsAndNoAffected(): void
    {
        // Arrange
        $result = new Result();

        // Act & Assert
        $this->assertTrue($result->isEmpty());
    }

    public function testIsEmptyFalseWithRows(): void
    {
        // Arrange
        $result = new Result(rows: [['id' => 1]]);

        // Act & Assert
        $this->assertFalse($result->isEmpty());
    }

    public function testIsEmptyFalseWithAffectedRows(): void
    {
        // Arrange
        $result = new Result(affectedRows: 3);

        // Act & Assert
        $this->assertFalse($result->isEmpty());
    }

    public function testAffectedRows(): void
    {
        // Arrange
        $result = new Result(affectedRows: 5);

        // Act & Assert
        $this->assertSame(5, $result->affectedRows());
    }

    public function testLastInsertId(): void
    {
        // Arrange
        $result = new Result(lastInsertId: '42');

        // Act & Assert
        $this->assertSame('42', $result->lastInsertId());
    }

    public function testCount(): void
    {
        // Arrange
        $rows = [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ];

        // Act
        $result = new Result(rows: $rows);

        // Assert
        $this->assertSame(3, $result->count());
    }
}
