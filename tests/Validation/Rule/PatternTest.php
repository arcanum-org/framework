<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation\Rule;

use Arcanum\Validation\Rule\Pattern;
use Arcanum\Validation\ValidationError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Pattern::class)]
#[UsesClass(ValidationError::class)]
final class PatternTest extends TestCase
{
    public function testMatchingPatternPasses(): void
    {
        $rule = new Pattern('/^[a-z]+$/');

        $this->assertNull($rule->validate('hello', 'slug'));
    }

    public function testNonMatchingPatternFails(): void
    {
        $rule = new Pattern('/^[a-z]+$/');

        $error = $rule->validate('Hello123', 'slug');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('slug', $error->field);
        $this->assertSame('The slug field format is invalid.', $error->message);
    }

    public function testPhonePattern(): void
    {
        $rule = new Pattern('/^\+?[0-9\-\s]{7,15}$/');

        $this->assertNull($rule->validate('+1-555-123-4567', 'phone'));
        $this->assertInstanceOf(ValidationError::class, $rule->validate('abc', 'phone'));
    }

    public function testNonStringSkipped(): void
    {
        $rule = new Pattern('/^[a-z]+$/');

        $this->assertNull($rule->validate(42, 'slug'));
    }
}
