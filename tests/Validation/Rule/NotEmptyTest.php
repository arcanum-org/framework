<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation\Rule;

use Arcanum\Validation\Rule\NotEmpty;
use Arcanum\Validation\ValidationError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(NotEmpty::class)]
#[UsesClass(ValidationError::class)]
final class NotEmptyTest extends TestCase
{
    public function testNonEmptyStringPasses(): void
    {
        $rule = new NotEmpty();

        $this->assertNull($rule->validate('hello', 'name'));
    }

    public function testNonEmptyArrayPasses(): void
    {
        $rule = new NotEmpty();

        $this->assertNull($rule->validate(['a'], 'items'));
    }

    public function testIntegerPasses(): void
    {
        $rule = new NotEmpty();

        $this->assertNull($rule->validate(0, 'count'));
    }

    public function testNullFails(): void
    {
        $rule = new NotEmpty();

        $error = $rule->validate(null, 'name');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('name', $error->field);
        $this->assertSame('The name field is required.', $error->message);
    }

    public function testEmptyStringFails(): void
    {
        $rule = new NotEmpty();

        $error = $rule->validate('', 'email');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('email', $error->field);
    }

    public function testEmptyArrayFails(): void
    {
        $rule = new NotEmpty();

        $error = $rule->validate([], 'items');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('items', $error->field);
    }
}
