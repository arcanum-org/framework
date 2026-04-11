<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation\Rule;

use Arcanum\Validation\Rule\MinLength;
use Arcanum\Validation\ValidationError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MinLength::class)]
#[UsesClass(ValidationError::class)]
final class MinLengthTest extends TestCase
{
    public function testStringAtMinPasses(): void
    {
        $rule = new MinLength(3);

        $this->assertNull($rule->validate('abc', 'name'));
    }

    public function testStringAboveMinPasses(): void
    {
        $rule = new MinLength(3);

        $this->assertNull($rule->validate('abcdef', 'name'));
    }

    public function testStringBelowMinFails(): void
    {
        $rule = new MinLength(3);

        $error = $rule->validate('ab', 'name');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('name', $error->field);
        $this->assertSame('The name field must be at least 3 characters.', $error->message);
    }

    public function testEmptyStringFailsWithMinOne(): void
    {
        $rule = new MinLength(1);

        $this->assertInstanceOf(ValidationError::class, $rule->validate('', 'name'));
    }

    public function testNonStringSkipped(): void
    {
        $rule = new MinLength(3);

        $this->assertNull($rule->validate(42, 'name'));
    }

    public function testMultibyteStringMeasuredCorrectly(): void
    {
        $rule = new MinLength(3);

        $this->assertNull($rule->validate('äöü', 'name'));
    }
}
