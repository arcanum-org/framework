<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Arcanum\Forge\Cast;

#[CoversClass(Cast::class)]
final class CastTest extends TestCase
{
    public function testEmptyCastMapReturnsIdentityClosure(): void
    {
        $apply = Cast::apply([]);

        $row = ['id' => '1', 'name' => 'Alice'];

        $this->assertSame($row, $apply($row));
    }

    public function testCastsMappedColumns(): void
    {
        $apply = Cast::apply(['id' => 'int', 'active' => 'bool']);

        $result = $apply([
            'id' => '42',
            'active' => '1',
            'name' => 'Alice',
        ]);

        $this->assertSame(42, $result['id']);
        $this->assertTrue($result['active']);
        $this->assertSame('Alice', $result['name']);
    }

    public function testUnmappedColumnsPassThrough(): void
    {
        $apply = Cast::apply(['id' => 'int']);

        $result = $apply(['id' => '7', 'extra' => 'untouched']);

        $this->assertSame('untouched', $result['extra']);
    }

    public function testMissingColumnsAreIgnored(): void
    {
        $apply = Cast::apply(['id' => 'int', 'count' => 'int']);

        $result = $apply(['id' => '3']);

        $this->assertSame(3, $result['id']);
        $this->assertArrayNotHasKey('count', $result);
    }

    public function testFloatCast(): void
    {
        $apply = Cast::apply(['price' => 'float']);

        $result = $apply(['price' => '9.99']);

        $this->assertSame(9.99, $result['price']);
    }

    public function testJsonCast(): void
    {
        $apply = Cast::apply(['payload' => 'json']);

        $result = $apply(['payload' => '{"k":"v"}']);

        $this->assertSame(['k' => 'v'], $result['payload']);
    }

    public function testClosureIsReusable(): void
    {
        $apply = Cast::apply(['id' => 'int']);

        $first = $apply(['id' => '1']);
        $second = $apply(['id' => '2']);

        $this->assertSame(1, $first['id']);
        $this->assertSame(2, $second['id']);
    }

    public function testNullValuePassesThrough(): void
    {
        $apply = Cast::apply(['id' => 'int']);

        $result = $apply(['id' => null]);

        $this->assertNull($result['id']);
    }

    public function testBoolTruthyVariants(): void
    {
        $apply = Cast::apply(['active' => 'bool']);

        $this->assertTrue($apply(['active' => '1'])['active']);
        $this->assertTrue($apply(['active' => 't'])['active']);
        $this->assertTrue($apply(['active' => 'true'])['active']);
        $this->assertTrue($apply(['active' => 'yes'])['active']);
    }

    public function testBoolFalsyVariants(): void
    {
        $apply = Cast::apply(['active' => 'bool']);

        $this->assertFalse($apply(['active' => '0'])['active']);
        $this->assertFalse($apply(['active' => 'f'])['active']);
        $this->assertFalse($apply(['active' => 'false'])['active']);
        $this->assertFalse($apply(['active' => 'no'])['active']);
    }

    public function testBoolPassesThroughActualBool(): void
    {
        $apply = Cast::apply(['active' => 'bool']);

        $this->assertTrue($apply(['active' => true])['active']);
        $this->assertFalse($apply(['active' => false])['active']);
    }

    public function testUnknownTypePassesThrough(): void
    {
        $apply = Cast::apply(['value' => 'someUnknownType']);

        $result = $apply(['value' => 'hello']);

        $this->assertSame('hello', $result['value']);
    }

    public function testNonScalarValuePassesThrough(): void
    {
        $apply = Cast::apply(['data' => 'int']);

        $object = new \stdClass();
        $result = $apply(['data' => $object]);

        $this->assertSame($object, $result['data']);
    }
}
