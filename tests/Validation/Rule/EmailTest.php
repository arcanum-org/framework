<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation\Rule;

use Arcanum\Validation\Rule\Email;
use Arcanum\Validation\ValidationError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Email::class)]
#[UsesClass(ValidationError::class)]
final class EmailTest extends TestCase
{
    public function testValidEmailPasses(): void
    {
        $rule = new Email();

        $this->assertNull($rule->validate('user@example.com', 'email'));
    }

    public function testEmailWithSubdomainPasses(): void
    {
        $rule = new Email();

        $this->assertNull($rule->validate('user@mail.example.com', 'email'));
    }

    public function testInvalidEmailFails(): void
    {
        $rule = new Email();

        $error = $rule->validate('not-an-email', 'email');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('email', $error->field);
        $this->assertSame('The email field must be a valid email address.', $error->message);
    }

    public function testEmptyStringFails(): void
    {
        $rule = new Email();

        $this->assertInstanceOf(ValidationError::class, $rule->validate('', 'email'));
    }

    public function testMissingAtSignFails(): void
    {
        $rule = new Email();

        $this->assertInstanceOf(ValidationError::class, $rule->validate('user.example.com', 'email'));
    }

    public function testNonStringSkipped(): void
    {
        $rule = new Email();

        $this->assertNull($rule->validate(42, 'email'));
    }
}
