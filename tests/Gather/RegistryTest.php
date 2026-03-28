<?php

declare(strict_types=1);

namespace Arcanum\Test\Gather;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Arcanum\Gather\Registry;

#[CoversClass(Registry::class)]
final class RegistryTest extends TestCase
{
    public function testCreate(): void
    {
        // Arrange
        $registry = new Registry();

        // Act
        $has = $registry->has('foo');
        $get = $registry->get('foo');

        // Assert
        $this->assertFalse($has);
        $this->assertNull($get);
    }

    public function testHasAndGet(): void
    {
        // Arrange
        $registry = new Registry(['foo' => 'bar']);

        // Act
        $has = $registry->has('foo');
        $get = $registry->get('foo');
        $nope = $registry->has('nope');

        // Assert
        $this->assertTrue($has);
        $this->assertSame('bar', $get);
        $this->assertFalse($nope);
    }

    public function testSerializeAndUnserialize(): void
    {
        // Arrange
        $registry = new Registry(['foo' => 'bar']);

        // Act
        $serialized = serialize($registry);
        $unserialized = unserialize($serialized);

        // Assert
        $this->assertEquals($registry, $unserialized);
        $this->assertInstanceOf(Registry::class, $unserialized);
        $this->assertEquals($registry->get('foo'), $unserialized->get('foo'));
    }

    public function testToArray(): void
    {
        // Arrange
        $registry = new Registry(['foo' => 'bar']);

        // Act
        $array = $registry->toArray();

        // Assert
        $this->assertEquals(['foo' => 'bar'], $array);
    }

    public function testToString(): void
    {
        // Arrange
        $registry = new Registry(['foo' => 'bar']);

        // Act
        $string = (string) $registry;

        // Assert
        $this->assertJsonStringEqualsJsonString('{"foo":"bar"}', $string);
    }

    public function testJsonEncodeAndDecode(): void
    {
        // Arrange
        $registry = new Registry(['foo' => 'bar']);

        // Act
        $encoded = json_encode($registry) ?: '';
        $decoded = json_decode($encoded, true);

        // Assert
        $this->assertJsonStringEqualsJsonString('{"foo":"bar"}', $encoded);
        $this->assertEquals(['foo' => 'bar'], $decoded);
    }

    public function testAsIterator(): void
    {
        // Arrange
        $registry = new Registry(['foo' => 'bar']);

        // Act & Assert
        foreach ($registry as $key => $value) {
            $this->assertSame('foo', $key);
            $this->assertSame('bar', $value);
        }
    }

    public function testCount(): void
    {
        // Arrange
        $registry = new Registry(['foo' => 'bar']);

        // Act
        $count = count($registry);

        // Assert
        $this->assertSame(1, $count);
    }

    public function testSet(): void
    {
        // Arrange
        $registry = new Registry(['foo' => 'bar']);

        // Act
        $registry->set('foo', 'baz');
        $get = $registry->get('foo');

        // Assert
        $this->assertSame('baz', $get);
    }

    public function testGetSetViaArrayAccess(): void
    {
        // Arrange
        $registry = new Registry(['foo' => 'bar']);

        // Act
        $set = isset($registry['foo']);
        $registry['foo'] = 'baz';
        $get = $registry['foo'];
        unset($registry['foo']);
        $empty = $registry['foo'];

        // Assert
        $this->assertSame('baz', $get);
        $this->assertTrue($set);
        $this->assertNull($empty);
    }

    public function testOffsetSetGetExistsUnset(): void
    {
        // Arrange
        $registry = new Registry(['foo' => 'bar']);

        // Act
        $set = $registry->offsetExists('foo');
        $registry->offsetSet('foo', 'baz');
        $get = $registry->offsetGet('foo');
        $registry->offsetUnset('foo');
        $empty = $registry->offsetGet('foo');

        // Assert
        $this->assertSame('baz', $get);
        $this->assertTrue($set);
        $this->assertNull($empty);
    }

    public function testGetAlpha(): void
    {
        // Arrange
        $registry = new Registry(['foo' => '1b1a@1r1']);

        // Act
        $get = $registry->asAlpha('foo');

        // Assert
        $this->assertSame('bar', $get);
    }

    public function testGetAlnum(): void
    {
        // Arrange
        $registry = new Registry(['foo' => '1b1a@1r1']);

        // Act
        $get = $registry->asAlnum('foo');

        // Assert
        $this->assertSame('1b1a1r1', $get);
    }

    public function testGetDigits(): void
    {
        // Arrange
        $registry = new Registry(['foo' => '1b1a@1r1']);

        // Act
        $get = $registry->asDigits('foo');

        // Assert
        $this->assertSame('1111', $get);
    }

    public function testGetString(): void
    {
        // Arrange
        $registry = new Registry(['foo' => '1b1a@1r1']);

        // Act
        $get = $registry->asString('foo');

        // Assert
        $this->assertSame('1b1a@1r1', $get);
    }

    public function testGetStringForNullValue(): void
    {
        // Arrange
        $registry = new Registry(['foo' => null]);

        // Act
        $get = $registry->asString('foo', 'bar');

        // Assert
        $this->assertSame('bar', $get);
    }

    public function testGetStringForObjectThatDefinesToString(): void
    {
        // Arrange
        $registry = new Registry(['foo' => new class () {
            public function __toString(): string
            {
                return 'bar';
            }
        }]);

        // Act
        $get = $registry->asString('foo');

        // Assert
        $this->assertSame('bar', $get);
    }

    public function testGetStringForArray(): void
    {
        // Arrange
        $registry = new Registry(['foo' => ['bar']]);

        // Act
        $get = $registry->asString('foo');

        // Assert
        $this->assertJsonStringEqualsJsonString('["bar"]', $get);
    }

    public function testGetStringReturnsDefaultIfCannotCoerceToString(): void
    {
        // Arrange
        $registry = new Registry(['foo' => new class () {
        }]);

        // Act
        $get = $registry->asString('foo', 'bar');

        // Assert
        $this->assertSame('bar', $get);
    }

    public function testGetInt(): void
    {
        // Arrange
        $registry = new Registry(['foo' => '10101']);

        // Act
        $get = $registry->asInt('foo');

        // Assert
        $this->assertSame(10101, $get);
    }

    public function testGetIntReturnsDefaultIfCannotCoerceIntoInt(): void
    {
        // Arrange
        $registry = new Registry(['foo' => new class (){
        }]);

        // Act
        $get = $registry->asInt('foo', 10);

        // Assert
        $this->assertSame(10, $get);
    }

    public function testGetFloat(): void
    {
        // Arrange
        $registry = new Registry(['foo' => '1b1a@1r1']);

        // Act
        $get = $registry->asFloat('foo');

        // Assert
        $this->assertSame(1.0, $get);
    }

    public function testGetFloatReturnsDefaultIfCannotCoerceIntoFloat(): void
    {
        // Arrange
        $registry = new Registry(['foo' => new class (){
        }]);

        // Act
        $get = $registry->asFloat('foo', 10.0);

        // Assert
        $this->assertSame(10.0, $get);
    }

    public function testGetBool(): void
    {
        // Arrange
        $registry = new Registry(['foo' => '1b1a@1r1']);

        // Act
        $get = $registry->asBool('foo');

        // Assert
        $this->assertTrue($get);
    }
}
