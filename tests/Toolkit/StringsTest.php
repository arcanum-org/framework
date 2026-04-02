<?php

declare(strict_types=1);

namespace Arcanum\Test\Toolkit;

use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Strings::class)]
final class StringsTest extends TestCase
{
    public function testAscii(): void
    {
        $this->assertSame('aouAOUss', Strings::ascii('äöüÄÖÜß'));
    }

    public function testCamel(): void
    {
        $this->assertSame('fooBarQ', Strings::camel('foo bar q'));
    }

    public function testKebab(): void
    {
        $this->assertSame('f-oo-bar-q', Strings::kebab('fOo bar q'));
    }

    public function testLinked(): void
    {
        $this->assertSame('foo*b*ar*q', Strings::linked('foo bAr Q', '*'));
        $this->assertSame('foobarq', Strings::linked('foobarq', '-'));
    }

    public function testPascal(): void
    {
        $this->assertSame('FooBarQ', Strings::pascal('foo bar q'));
    }

    public function testSnake(): void
    {
        $this->assertSame('foo_bar_q_ux', Strings::snake('foo bar qUx'));
    }

    public function testTitle(): void
    {
        $this->assertSame('Foo Bar Q', Strings::title('foo bar q'));
    }

    public function testClassNamespace(): void
    {
        $this->assertSame(
            'App\\Domain\\Shop',
            Strings::classNamespace('App\\Domain\\Shop\\PlaceOrder'),
        );
    }

    public function testClassNamespaceReturnsEmptyForUnqualified(): void
    {
        $this->assertSame('', Strings::classNamespace('PlaceOrder'));
    }

    public function testClassBaseName(): void
    {
        $this->assertSame(
            'PlaceOrder',
            Strings::classBaseName('App\\Domain\\Shop\\PlaceOrder'),
        );
    }

    public function testClassBaseNameReturnsFullForUnqualified(): void
    {
        $this->assertSame('PlaceOrder', Strings::classBaseName('PlaceOrder'));
    }

    public function testStripNamespacePrefix(): void
    {
        $this->assertSame(
            'Shop\\Command\\PlaceOrder',
            Strings::stripNamespacePrefix(
                'App\\Domain\\Shop\\Command\\PlaceOrder',
                'App\\Domain',
            ),
        );
    }

    public function testStripNamespacePrefixWithTrailingBackslash(): void
    {
        $this->assertSame(
            'Shop\\PlaceOrder',
            Strings::stripNamespacePrefix(
                'App\\Domain\\Shop\\PlaceOrder',
                'App\\Domain\\',
            ),
        );
    }

    public function testStripNamespacePrefixThrowsOnMismatch(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('is not under namespace prefix');
        Strings::stripNamespacePrefix('Other\\Foo', 'App\\Domain');
    }
}
