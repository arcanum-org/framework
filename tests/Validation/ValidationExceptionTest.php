<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation;

use Arcanum\Validation\ValidationError;
use Arcanum\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ValidationException::class)]
#[UsesClass(ValidationError::class)]
final class ValidationExceptionTest extends TestCase
{
    public function testErrorsReturnsAllErrors(): void
    {
        $errors = [
            new ValidationError('name', 'The name field is required.'),
            new ValidationError('email', 'The email field must be a valid email address.'),
        ];

        $exception = new ValidationException($errors);

        $this->assertCount(2, $exception->errors());
        $this->assertSame('name', $exception->errors()[0]->field);
        $this->assertSame('email', $exception->errors()[1]->field);
    }

    public function testErrorsByFieldGroupsMessages(): void
    {
        $errors = [
            new ValidationError('name', 'The name field is required.'),
            new ValidationError('name', 'The name field must be at least 3 characters.'),
            new ValidationError('email', 'The email field must be a valid email address.'),
        ];

        $exception = new ValidationException($errors);
        $grouped = $exception->errorsByField();

        $this->assertCount(2, $grouped);
        $this->assertCount(2, $grouped['name']);
        $this->assertCount(1, $grouped['email']);
        $this->assertSame('The name field is required.', $grouped['name'][0]);
        $this->assertSame('The name field must be at least 3 characters.', $grouped['name'][1]);
    }

    public function testMessageContainsErrorCount(): void
    {
        $errors = [
            new ValidationError('a', 'fail'),
            new ValidationError('b', 'fail'),
            new ValidationError('c', 'fail'),
        ];

        $exception = new ValidationException($errors);

        $this->assertSame('Validation failed with 3 error(s).', $exception->getMessage());
    }
}
