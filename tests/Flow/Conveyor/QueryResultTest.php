<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor;

use Arcanum\Flow\Conveyor\QueryResult;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(QueryResult::class)]
final class QueryResultTest extends TestCase
{
    public function testWrapsArray(): void
    {
        // Arrange & Act
        $result = new QueryResult(['key' => 'value', 'count' => 42]);

        // Assert
        $this->assertSame(['key' => 'value', 'count' => 42], $result->data);
    }

    public function testWrapsString(): void
    {
        // Arrange & Act
        $result = new QueryResult('hello');

        // Assert
        $this->assertSame('hello', $result->data);
    }

    public function testWrapsInteger(): void
    {
        // Arrange & Act
        $result = new QueryResult(42);

        // Assert
        $this->assertSame(42, $result->data);
    }

    public function testWrapsNull(): void
    {
        // Arrange & Act
        $result = new QueryResult(null);

        // Assert
        $this->assertNull($result->data);
    }

    public function testWrapsBool(): void
    {
        // Arrange & Act
        $result = new QueryResult(true);

        // Assert
        $this->assertTrue($result->data);
    }

    public function testWrapsFloat(): void
    {
        // Arrange & Act
        $result = new QueryResult(3.14);

        // Assert
        $this->assertSame(3.14, $result->data);
    }
}
