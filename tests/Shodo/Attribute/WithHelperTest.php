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
        $attribute = new WithHelper(\stdClass::class, 'Std');

        $this->assertSame(\stdClass::class, $attribute->className);
    }

    public function testStoresAlias(): void
    {
        $attribute = new WithHelper(\stdClass::class, 'Std');

        $this->assertSame('Std', $attribute->alias);
    }
}
