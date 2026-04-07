<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Attribute;

use Arcanum\Shodo\Attribute\WithHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(WithHelper::class)]
final class WithHelperTest extends TestCase
{
    public function testStoresClassName(): void
    {
        $attribute = new WithHelper(\stdClass::class);

        $this->assertSame(\stdClass::class, $attribute->className);
    }

    public function testExplicitAliasIsReturned(): void
    {
        $attribute = new WithHelper(\stdClass::class, alias: 'Std');

        $this->assertSame('Std', $attribute->resolvedAlias());
    }

    public function testAliasStripsHelperSuffixFromBasename(): void
    {
        $attribute = new WithHelper('App\\Helpers\\EnvCheckHelper');

        $this->assertSame('EnvCheck', $attribute->resolvedAlias());
    }

    public function testAliasUsesBasenameWhenNoHelperSuffix(): void
    {
        $attribute = new WithHelper('App\\Helpers\\Toolbox');

        $this->assertSame('Toolbox', $attribute->resolvedAlias());
    }

    public function testAliasFallsBackToFullNameWhenNoNamespace(): void
    {
        $attribute = new WithHelper('Toolbox');

        $this->assertSame('Toolbox', $attribute->resolvedAlias());
    }

    public function testAliasDoesNotStripBareHelperName(): void
    {
        // A class literally named "Helper" shouldn't become an empty alias.
        $attribute = new WithHelper('App\\Helpers\\Helper');

        $this->assertSame('Helper', $attribute->resolvedAlias());
    }

    public function testExplicitAliasOverridesAutoStrip(): void
    {
        $attribute = new WithHelper('App\\Helpers\\EnvCheckHelper', alias: 'Env');

        $this->assertSame('Env', $attribute->resolvedAlias());
    }
}
