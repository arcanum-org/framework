<?php

declare(strict_types=1);

namespace Arcanum\Test\Testing;

use Arcanum\Test\Fixture\Testing\CallbackDto;
use Arcanum\Test\Fixture\Testing\NestedDto;
use Arcanum\Test\Fixture\Testing\NullableDto;
use Arcanum\Test\Fixture\Testing\PatternDto;
use Arcanum\Test\Fixture\Testing\SimpleDto;
use Arcanum\Testing\Factory;
use Arcanum\Testing\FactoryException;
use Arcanum\Validation\Rule\Email;
use Arcanum\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Factory::class)]
#[CoversClass(FactoryException::class)]
final class FactoryTest extends TestCase
{
    public function testHappyPathProducesValidDtoFromAttributes(): void
    {
        $factory = new Factory();

        $dto = $factory->make(SimpleDto::class);

        $this->assertInstanceOf(SimpleDto::class, $dto);
        // The synthesized values should pass validation when run through Validator.
        $errors = (new Validator())->check($dto);
        $this->assertSame([], $errors, 'Synthesized DTO failed validation: '
            . implode(', ', array_map(fn($e) => $e->message, $errors)));
        // Spot-check a few specific synthesized values.
        $this->assertSame('test@example.com', $dto->email);
        $this->assertSame('https://example.com', $dto->homepage);
        $this->assertSame('00000000-0000-4000-8000-000000000000', $dto->id);
        $this->assertSame('red', $dto->color);
        $this->assertGreaterThanOrEqual(18, $dto->age);
        $this->assertLessThanOrEqual(99, $dto->age);
        $this->assertGreaterThanOrEqual(8, mb_strlen($dto->username));
        $this->assertLessThanOrEqual(12, mb_strlen($dto->username));
    }

    public function testOverridesTakePrecedence(): void
    {
        $factory = new Factory();

        $dto = $factory->make(SimpleDto::class, [
            'name' => 'Alice',
            'email' => 'alice@arcanum.dev',
            'age' => 42,
        ]);

        $this->assertSame('Alice', $dto->name);
        $this->assertSame('alice@arcanum.dev', $dto->email);
        $this->assertSame(42, $dto->age);
    }

    public function testDefaultsAreUsedWhenNoOverrideProvided(): void
    {
        $factory = new Factory();

        $dto = $factory->make(SimpleDto::class);

        // $message has a default of 'default' — Factory must NOT synthesize
        // a value, it should let Hydrator use the default.
        $this->assertSame('default', $dto->message);
    }

    public function testRecursesIntoNestedDtoParameters(): void
    {
        $factory = new Factory();

        $dto = $factory->make(NestedDto::class);

        $this->assertInstanceOf(NestedDto::class, $dto);
        $this->assertInstanceOf(SimpleDto::class, $dto->inner);
        $this->assertSame('test@example.com', $dto->inner->email);
    }

    public function testNullableScalarWithoutRulesGetsNull(): void
    {
        $factory = new Factory();

        $dto = $factory->make(NullableDto::class);

        $this->assertNull($dto->note);
        $this->assertSame(1, $dto->count);
    }

    public function testPatternRuleThrowsFactoryException(): void
    {
        $factory = new Factory();

        $this->expectException(FactoryException::class);
        $this->expectExceptionMessage('Pattern rules are not auto-generatable');

        $factory->make(PatternDto::class);
    }

    public function testPatternDtoCanBeBuiltWithExplicitOverride(): void
    {
        $factory = new Factory();

        $dto = $factory->make(PatternDto::class, ['code' => 'ABC-1234']);

        $this->assertSame('ABC-1234', $dto->code);
    }

    public function testCallbackRuleThrowsFactoryException(): void
    {
        $factory = new Factory();

        $this->expectException(FactoryException::class);
        $this->expectExceptionMessage('Callback rules are not auto-generatable');

        $factory->make(CallbackDto::class);
    }

    public function testZeroParameterClassReturnsBareInstance(): void
    {
        $factory = new Factory();

        $object = $factory->make(\stdClass::class);

        $this->assertInstanceOf(\stdClass::class, $object);
    }

    public function testEmailAttributeProducesValidEmail(): void
    {
        // Sanity-check the Factory's email synthesis against the real Email rule.
        $factory = new Factory();
        $dto = $factory->make(SimpleDto::class);

        $rule = new Email();
        $this->assertNull($rule->validate($dto->email, 'email'));
    }
}
