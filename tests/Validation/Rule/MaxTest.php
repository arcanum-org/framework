<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation\Rule;

use Arcanum\Validation\Rule\Max;
use Arcanum\Validation\ValidationError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Max::class)]
#[UsesClass(ValidationError::class)]
final class MaxTest extends TestCase
{
    public function testValueAtMaxPasses(): void
    {
        $rule = new Max(100);

        $this->assertNull($rule->validate(100, 'quantity'));
    }

    public function testValueBelowMaxPasses(): void
    {
        $rule = new Max(100);

        $this->assertNull($rule->validate(50, 'quantity'));
    }

    public function testValueAboveMaxFails(): void
    {
        $rule = new Max(100);

        $error = $rule->validate(101, 'quantity');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('quantity', $error->field);
        $this->assertSame('The quantity field must not exceed 100.', $error->message);
    }

    public function testFloatValuePasses(): void
    {
        $rule = new Max(9.99);

        $this->assertNull($rule->validate(9.99, 'price'));
    }

    public function testFloatValueFails(): void
    {
        $rule = new Max(9.99);

        $this->assertInstanceOf(ValidationError::class, $rule->validate(10.0, 'price'));
    }

    public function testNonNumericSkipped(): void
    {
        $rule = new Max(100);

        $this->assertNull($rule->validate('hello', 'quantity'));
    }
}
