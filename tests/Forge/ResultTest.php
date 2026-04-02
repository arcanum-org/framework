<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Forge\Result;
use Arcanum\Forge\Sql;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Result::class)]
#[UsesClass(Sql::class)]
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

    public function testWithCastsAppliesOnRows(): void
    {
        // Arrange
        $result = new Result(rows: [
            ['id' => '1', 'price' => '9.99', 'active' => '1'],
            ['id' => '2', 'price' => '19.99', 'active' => '0'],
        ]);

        // Act
        $cast = $result->withCasts([
            'id' => 'int',
            'price' => 'float',
            'active' => 'bool',
        ]);

        // Assert
        $rows = $cast->rows();
        $this->assertSame(1, $rows[0]['id']);
        $this->assertSame(9.99, $rows[0]['price']);
        $this->assertTrue($rows[0]['active']);
        $this->assertSame(2, $rows[1]['id']);
        $this->assertFalse($rows[1]['active']);
    }

    public function testWithCastsAppliesOnFirst(): void
    {
        // Arrange
        $result = new Result(rows: [['count' => '42']]);

        // Act
        $cast = $result->withCasts(['count' => 'int']);

        // Assert
        $this->assertNotNull($cast->first());
        $this->assertSame(42, $cast->first()['count']);
    }

    public function testWithCastsAppliesOnScalar(): void
    {
        // Arrange
        $result = new Result(rows: [['total' => '100']]);

        // Act
        $cast = $result->withCasts(['total' => 'int']);

        // Assert
        $this->assertSame(100, $cast->scalar());
    }

    public function testWithCastsDoesNotMutateOriginal(): void
    {
        // Arrange
        $result = new Result(rows: [['id' => '1']]);

        // Act
        $result->withCasts(['id' => 'int']);

        // Assert — original is unchanged
        $this->assertNotNull($result->first());
        $this->assertSame('1', $result->first()['id']);
    }
}
