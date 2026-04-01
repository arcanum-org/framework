<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation\Rule;

use Arcanum\Validation\Rule\Min;
use Arcanum\Validation\ValidationError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Min::class)]
#[UsesClass(ValidationError::class)]
final class MinTest extends TestCase
{
    public function testValueAtMinPasses(): void
    {
        $rule = new Min(10);

        $this->assertNull($rule->validate(10, 'age'));
    }

    public function testValueAboveMinPasses(): void
    {
        $rule = new Min(10);

        $this->assertNull($rule->validate(20, 'age'));
    }

    public function testValueBelowMinFails(): void
    {
        $rule = new Min(10);

        $error = $rule->validate(5, 'age');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('age', $error->field);
        $this->assertSame('The age field must be at least 10.', $error->message);
    }

    public function testFloatValuePasses(): void
    {
        $rule = new Min(1.5);

        $this->assertNull($rule->validate(1.5, 'price'));
    }

    public function testFloatValueFails(): void
    {
        $rule = new Min(1.5);

        $this->assertInstanceOf(ValidationError::class, $rule->validate(1.4, 'price'));
    }

    public function testNonNumericSkipped(): void
    {
        $rule = new Min(10);

        $this->assertNull($rule->validate('hello', 'age'));
    }

    public function testZeroBoundary(): void
    {
        $rule = new Min(0);

        $this->assertNull($rule->validate(0, 'count'));
        $this->assertInstanceOf(ValidationError::class, $rule->validate(-1, 'count'));
    }
}
