<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation\Rule;

use Arcanum\Validation\Rule\MaxLength;
use Arcanum\Validation\ValidationError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MaxLength::class)]
#[UsesClass(ValidationError::class)]
final class MaxLengthTest extends TestCase
{
    public function testStringAtMaxPasses(): void
    {
        $rule = new MaxLength(5);

        $this->assertNull($rule->validate('abcde', 'name'));
    }

    public function testStringBelowMaxPasses(): void
    {
        $rule = new MaxLength(5);

        $this->assertNull($rule->validate('ab', 'name'));
    }

    public function testStringAboveMaxFails(): void
    {
        $rule = new MaxLength(5);

        $error = $rule->validate('abcdef', 'name');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('name', $error->field);
        $this->assertSame('The name field must not exceed 5 characters.', $error->message);
    }

    public function testEmptyStringPassesWithMaxZero(): void
    {
        $rule = new MaxLength(0);

        $this->assertNull($rule->validate('', 'name'));
    }

    public function testNonStringSkipped(): void
    {
        $rule = new MaxLength(5);

        $this->assertNull($rule->validate(42, 'name'));
    }

    public function testMultibyteStringMeasuredCorrectly(): void
    {
        $rule = new MaxLength(3);

        $this->assertNull($rule->validate('äöü', 'name'));
    }
}
