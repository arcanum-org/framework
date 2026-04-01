<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation\Rule;

use Arcanum\Validation\Rule\Uuid;
use Arcanum\Validation\ValidationError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Uuid::class)]
#[UsesClass(ValidationError::class)]
final class UuidTest extends TestCase
{
    public function testValidUuidV4Passes(): void
    {
        $rule = new Uuid();

        $this->assertNull($rule->validate('550e8400-e29b-41d4-a716-446655440000', 'id'));
    }

    public function testValidUuidUppercasePasses(): void
    {
        $rule = new Uuid();

        $this->assertNull($rule->validate('550E8400-E29B-41D4-A716-446655440000', 'id'));
    }

    public function testNilUuidPasses(): void
    {
        $rule = new Uuid();

        $this->assertNull($rule->validate('00000000-0000-0000-0000-000000000000', 'id'));
    }

    public function testInvalidUuidFails(): void
    {
        $rule = new Uuid();

        $error = $rule->validate('not-a-uuid', 'id');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('id', $error->field);
        $this->assertSame('The id field must be a valid UUID.', $error->message);
    }

    public function testUuidWithoutDashesFails(): void
    {
        $rule = new Uuid();

        $this->assertInstanceOf(
            ValidationError::class,
            $rule->validate('550e8400e29b41d4a716446655440000', 'id'),
        );
    }

    public function testEmptyStringFails(): void
    {
        $rule = new Uuid();

        $this->assertInstanceOf(ValidationError::class, $rule->validate('', 'id'));
    }

    public function testNonStringSkipped(): void
    {
        $rule = new Uuid();

        $this->assertNull($rule->validate(42, 'id'));
    }
}
