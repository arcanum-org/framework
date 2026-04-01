<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation\Rule;

use Arcanum\Validation\Rule\In;
use Arcanum\Validation\ValidationError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(In::class)]
#[UsesClass(ValidationError::class)]
final class InTest extends TestCase
{
    public function testValueInListPasses(): void
    {
        $rule = new In('draft', 'published', 'archived');

        $this->assertNull($rule->validate('published', 'status'));
    }

    public function testValueNotInListFails(): void
    {
        $rule = new In('draft', 'published', 'archived');

        $error = $rule->validate('deleted', 'status');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('status', $error->field);
        $this->assertSame('The status field must be one of: draft, published, archived.', $error->message);
    }

    public function testStrictTypeComparison(): void
    {
        $rule = new In(1, 2, 3);

        $this->assertNull($rule->validate(1, 'level'));
        $this->assertInstanceOf(ValidationError::class, $rule->validate('1', 'level'));
    }

    public function testIntegerValues(): void
    {
        $rule = new In(10, 20, 30);

        $this->assertNull($rule->validate(20, 'size'));
        $this->assertInstanceOf(ValidationError::class, $rule->validate(15, 'size'));
    }
}
