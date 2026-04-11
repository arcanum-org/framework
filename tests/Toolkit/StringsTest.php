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

    public function testTruncateShorterThanLimit(): void
    {
        $this->assertSame('hello', Strings::truncate('hello', 10));
    }

    public function testTruncateExactlyAtLimit(): void
    {
        $this->assertSame('hello', Strings::truncate('hello', 5));
    }

    public function testTruncateWithDefaultSuffix(): void
    {
        $result = Strings::truncate('Hello, world!', 10);
        $this->assertSame('Hello, ...', $result);
        $this->assertSame(10, mb_strlen($result));
    }

    public function testTruncateWithCustomSuffix(): void
    {
        $result = Strings::truncate('Hello, world!', 10, '~');
        $this->assertSame('Hello, wo~', $result);
    }

    public function testTruncateWithMultibyte(): void
    {
        $result = Strings::truncate('こんにちは世界', 5, '…');
        $this->assertSame('こんにち…', $result);
        $this->assertSame(5, mb_strlen($result));
    }

    public function testTruncateEmptyString(): void
    {
        $this->assertSame('', Strings::truncate('', 10));
    }

    public function testLower(): void
    {
        $this->assertSame('hello world', Strings::lower('Hello WORLD'));
    }

    public function testLowerMultibyte(): void
    {
        $this->assertSame('äöü', Strings::lower('ÄÖÜ'));
    }

    public function testUpper(): void
    {
        $this->assertSame('HELLO WORLD', Strings::upper('Hello world'));
    }

    public function testUpperMultibyte(): void
    {
        $this->assertSame('STRASSE', Strings::upper('strasse'));
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

    public function testNamespacePath(): void
    {
        $this->assertSame(
            'app' . DIRECTORY_SEPARATOR . 'Domain',
            Strings::namespacePath('App\\Domain'),
        );
    }

    public function testNamespacePathNested(): void
    {
        $this->assertSame(
            'app' . DIRECTORY_SEPARATOR . 'Domain' . DIRECTORY_SEPARATOR . 'Shop',
            Strings::namespacePath('App\\Domain\\Shop'),
        );
    }

    public function testNamespacePathSingleSegment(): void
    {
        $this->assertSame('vendor', Strings::namespacePath('Vendor'));
    }

    // -----------------------------------------------------------
    // closestMatch()
    // -----------------------------------------------------------

    public function testClosestMatchFindsExactMatch(): void
    {
        $this->assertSame(
            'FindAll',
            Strings::closestMatch('FindAll', ['FindAll', 'Save', 'Delete']),
        );
    }

    public function testClosestMatchFindsSimilar(): void
    {
        $this->assertSame(
            'FindAll',
            Strings::closestMatch('FindAl', ['FindAll', 'Save', 'Delete']),
        );
    }

    public function testClosestMatchIsCaseInsensitive(): void
    {
        $this->assertSame(
            'FindAll',
            Strings::closestMatch('findall', ['FindAll', 'Save', 'Delete']),
        );
    }

    public function testClosestMatchReturnsNullWhenNoCandidates(): void
    {
        $this->assertNull(Strings::closestMatch('foo', []));
    }

    public function testClosestMatchReturnsNullWhenTooDistant(): void
    {
        $this->assertNull(
            Strings::closestMatch('xyz', ['FindAll', 'Save', 'Delete']),
        );
    }

    public function testClosestMatchPicksNearest(): void
    {
        $this->assertSame(
            'Save',
            Strings::closestMatch('Sve', ['FindAll', 'Save', 'Delete']),
        );
    }
}
