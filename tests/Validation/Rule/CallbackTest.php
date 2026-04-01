<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation\Rule;

use Arcanum\Validation\Rule\Callback;
use Arcanum\Validation\ValidationError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Callback::class)]
#[UsesClass(ValidationError::class)]
final class CallbackTest extends TestCase
{
    public function testReturnsTrueWhenCallbackReturnsTrue(): void
    {
        $rule = new Callback(fn(mixed $v): true => true);

        $this->assertNull($rule->validate('anything', 'field'));
    }

    public function testReturnsErrorWhenCallbackReturnsString(): void
    {
        $rule = new Callback(fn(mixed $v): string => 'Custom error message.');

        $error = $rule->validate('bad', 'code');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('code', $error->field);
        $this->assertSame('Custom error message.', $error->message);
    }

    public function testCallbackReceivesValue(): void
    {
        $received = null;
        $rule = new Callback(function (mixed $v) use (&$received): true {
            $received = $v;
            return true;
        });

        $rule->validate('test-value', 'field');

        $this->assertSame('test-value', $received);
    }

    public function testWorksWithStaticMethod(): void
    {
        $rule = new Callback([self::class, 'exampleValidator']);

        $this->assertNull($rule->validate('good', 'field'));
        $this->assertInstanceOf(ValidationError::class, $rule->validate('bad', 'field'));
    }

    public static function exampleValidator(mixed $value): true|string
    {
        return $value === 'good' ? true : 'Value must be good.';
    }
}
