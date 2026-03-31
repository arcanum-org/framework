<?php

declare(strict_types=1);

namespace Arcanum\Test\Gather;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Gather\IgnoreCaseRegistry;
use Arcanum\Gather\Registry;

#[CoversClass(IgnoreCaseRegistry::class)]
#[UsesClass(Registry::class)]
final class IgnoreCaseRegistryTest extends TestCase
{
    public function testHasAndGet(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['foo' => 'bar']);

        // Act
        $has = $registry->has('fOo');
        $get = $registry->get('foO');
        $nope = $registry->has('nope');

        // Assert
        $this->assertTrue($has);
        $this->assertSame('bar', $get);
        $this->assertFalse($nope);
    }

    public function testGetSetViaArrayAccess(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['foo' => 'bar']);

        // Act
        $set = isset($registry['fOo']);
        $registry['Foo'] = 'baz';
        $get = $registry['foO'];
        unset($registry['FOO']);
        $empty = $registry['FoO'];

        // Assert
        $this->assertSame('baz', $get);
        $this->assertTrue($set);
        $this->assertNull($empty);
    }

    public function testOffsetSetGetExistsUnset(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['foo' => 'bar']);

        // Act
        $set = $registry->offsetExists('fOo');
        $registry->offsetSet('Foo', 'baz');
        $get = $registry->offsetGet('foO');
        $registry->offsetUnset('FOO');
        $empty = $registry->offsetGet('FoO');

        // Assert
        $this->assertSame('baz', $get);
        $this->assertTrue($set);
        $this->assertNull($empty);
    }

    public function testNewKeysArePreservedIfNotAddedByConstructor(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry();

        // Act
        $registry['baz'] = 'qux';
        $get = $registry['bAz'];

        // Assert
        $this->assertSame('qux', $get);
    }

    public function testGetString(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['foo' => '1b1a@1r1']);

        // Act
        $get = $registry->asString('FOo');

        // Assert
        $this->assertSame('1b1a@1r1', $get);
    }

    public function testGetInt(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['foo' => '1b1a@1r1']);

        // Act
        $get = $registry->asInt('FoO');

        // Assert
        $this->assertSame(1, $get);
    }

    public function testGetFloat(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['foo' => '1b1a@1r1']);

        // Act
        $get = $registry->asFloat('fOO');

        // Assert
        $this->assertSame(1.0, $get);
    }

    public function testGetBool(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['foo' => '1b1a@1r1']);

        // Act
        $get = $registry->asBool('foO');

        // Assert
        $this->assertTrue($get);
    }

    public function testSet(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry();

        // Act
        $registry->set('foo', 'bar');
        $get = $registry->get('FOO');

        // Assert
        $this->assertSame('bar', $get);
    }

    public function testGetKey(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['foo' => 'bar']);

        // Act
        $get = $registry->getKey('fOo');

        // Assert
        $this->assertSame('foo', $get);
    }

    public function testGetKeyNotSet(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['foo' => 'bar']);

        // Act
        $get = $registry->getKey('nope');

        // Assert
        $this->assertSame('nope', $get);
    }

    public function testSerializeUnserialize(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['foo' => 'bar']);

        // Act
        $serialized = serialize($registry);
        $unserialized = unserialize($serialized);

        // Assert
        $this->assertEquals($registry, $unserialized);
    }

    public function testToArray(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['foo' => 'bar']);

        // Act
        $array = $registry->toArray();

        // Assert
        $this->assertSame(['foo' => 'bar'], $array);
    }

    public function testAsAlphaCaseInsensitive(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['Content-Type' => '1a2p3p@']);

        // Act
        $alpha = $registry->asAlpha('CONTENT-TYPE');

        // Assert
        $this->assertSame('app', $alpha);
    }

    public function testAsAlnumCaseInsensitive(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['X-Rate' => '1a2b@3c']);

        // Act
        $alnum = $registry->asAlnum('x-rate');

        // Assert
        $this->assertSame('1a2b3c', $alnum);
    }

    public function testAsDigitsCaseInsensitive(): void
    {
        // Arrange
        $registry = new IgnoreCaseRegistry(['Content-Length' => '12ab34']);

        // Act
        $digits = $registry->asDigits('content-LENGTH');

        // Assert
        $this->assertSame('1234', $digits);
    }
}
