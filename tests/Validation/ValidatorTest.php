<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation;

use Arcanum\Validation\Rule\Email;
use Arcanum\Validation\Rule\MaxLength;
use Arcanum\Validation\Rule\MinLength;
use Arcanum\Validation\Rule\NotEmpty;
use Arcanum\Validation\ValidationError;
use Arcanum\Validation\ValidationException;
use Arcanum\Validation\Validator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Validator::class)]
#[UsesClass(ValidationError::class)]
#[UsesClass(ValidationException::class)]
#[UsesClass(NotEmpty::class)]
#[UsesClass(Email::class)]
#[UsesClass(MinLength::class)]
#[UsesClass(MaxLength::class)]
final class ValidatorTest extends TestCase
{
    public function testDtoWithAllValidValuesPasses(): void
    {
        $dto = new class ('John', 'john@example.com') {
            public function __construct(
                #[NotEmpty]
                public readonly string $name,
                #[NotEmpty] #[Email]
                public readonly string $email,
            ) {
            }
        };

        $validator = new Validator();

        $this->assertSame([], $validator->check($dto));
    }

    public function testValidateDoesNotThrowOnValidDto(): void
    {
        $dto = new class ('John', 'john@example.com') {
            public function __construct(
                #[NotEmpty]
                public readonly string $name,
                #[NotEmpty] #[Email]
                public readonly string $email,
            ) {
            }
        };

        $validator = new Validator();
        $validator->validate($dto);

        $this->assertSame([], $validator->check($dto));
    }

    public function testCollectsAllErrorsFromMultipleFields(): void
    {
        $dto = new class ('', 'not-an-email') {
            public function __construct(
                #[NotEmpty] #[MinLength(3)]
                public readonly string $name,
                #[NotEmpty] #[Email]
                public readonly string $email,
            ) {
            }
        };

        $validator = new Validator();
        $errors = $validator->check($dto);

        // name: NotEmpty fails, MinLength fails
        // email: Email fails (NotEmpty passes because 'not-an-email' is non-empty)
        $this->assertCount(3, $errors);

        $fields = array_map(fn(ValidationError $e) => $e->field, $errors);
        $this->assertSame(['name', 'name', 'email'], $fields);
    }

    public function testValidateThrowsOnInvalidDto(): void
    {
        $dto = new class ('') {
            public function __construct(
                #[NotEmpty]
                public readonly string $name,
            ) {
            }
        };

        $validator = new Validator();

        $this->expectException(ValidationException::class);
        $validator->validate($dto);
    }

    public function testDtoWithNoRulesPasses(): void
    {
        $dto = new class ('anything') {
            public function __construct(
                public readonly string $name,
            ) {
            }
        };

        $validator = new Validator();

        $this->assertSame([], $validator->check($dto));
    }

    public function testNullableNullValueSkipsRules(): void
    {
        $dto = new class (null) {
            public function __construct(
                #[NotEmpty] #[Email]
                public readonly string|null $email,
            ) {
            }
        };

        $validator = new Validator();

        $this->assertSame([], $validator->check($dto));
    }

    public function testNullableNonNullValueIsValidated(): void
    {
        $dto = new class ('bad') {
            public function __construct(
                #[Email]
                public readonly string|null $email,
            ) {
            }
        };

        $validator = new Validator();
        $errors = $validator->check($dto);

        $this->assertCount(1, $errors);
        $this->assertSame('email', $errors[0]->field);
    }

    public function testHandlerProxyIsSkipped(): void
    {
        $proxy = new class () implements \Arcanum\Flow\Conveyor\HandlerProxy {
            public function handlerBaseName(): string
            {
                return 'Some\\DTO';
            }
        };

        $validator = new Validator();

        $this->assertSame([], $validator->check($proxy));
    }

    public function testDtoWithNoConstructorPasses(): void
    {
        $dto = new class () {
        };

        $validator = new Validator();

        $this->assertSame([], $validator->check($dto));
    }

    public function testErrorMessagesContainFieldNames(): void
    {
        $dto = new class ('') {
            public function __construct(
                #[NotEmpty]
                public readonly string $username,
            ) {
            }
        };

        $validator = new Validator();
        $errors = $validator->check($dto);

        $this->assertCount(1, $errors);
        $this->assertSame('username', $errors[0]->field);
        $this->assertStringContainsString('username', $errors[0]->message);
    }

    public function testCheckReturnsErrorsWithoutThrowing(): void
    {
        $dto = new class ('') {
            public function __construct(
                #[NotEmpty]
                public readonly string $name,
            ) {
            }
        };

        $validator = new Validator();
        $errors = $validator->check($dto);

        $this->assertCount(1, $errors);
    }
}
