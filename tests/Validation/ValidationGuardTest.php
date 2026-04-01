<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation;

use Arcanum\Flow\Conveyor\HandlerProxy;
use Arcanum\Validation\Rule\NotEmpty;
use Arcanum\Validation\ValidationError;
use Arcanum\Validation\ValidationException;
use Arcanum\Validation\ValidationGuard;
use Arcanum\Validation\Validator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(ValidationGuard::class)]
#[UsesClass(Validator::class)]
#[UsesClass(ValidationError::class)]
#[UsesClass(ValidationException::class)]
#[UsesClass(NotEmpty::class)]
final class ValidationGuardTest extends TestCase
{
    public function testValidDtoPassesThroughAndCallsNext(): void
    {
        $dto = new class ('John') {
            public function __construct(
                #[NotEmpty]
                public readonly string $name,
            ) {
            }
        };

        $guard = new ValidationGuard();
        $called = false;

        $guard($dto, function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function testInvalidDtoThrowsBeforeNext(): void
    {
        $dto = new class ('') {
            public function __construct(
                #[NotEmpty]
                public readonly string $name,
            ) {
            }
        };

        $guard = new ValidationGuard();
        $called = false;

        $this->expectException(ValidationException::class);

        $guard($dto, function () use (&$called) {
            $called = true;
        });

        $this->assertFalse($called);
    }

    public function testHandlerProxyIsSkipped(): void
    {
        $proxy = new class () implements HandlerProxy {
            public function handlerBaseName(): string
            {
                return 'Some\\DTO';
            }
        };

        $guard = new ValidationGuard();
        $called = false;

        $guard($proxy, function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function testDtoWithNoRulesPassesThrough(): void
    {
        $dto = new class ('anything') {
            public function __construct(
                public readonly string $name,
            ) {
            }
        };

        $guard = new ValidationGuard();
        $called = false;

        $guard($dto, function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }
}
