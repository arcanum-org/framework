<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation\Rule;

use Arcanum\Validation\Rule\AnyUrl;
use Arcanum\Validation\ValidationError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(AnyUrl::class)]
#[UsesClass(ValidationError::class)]
final class AnyUrlTest extends TestCase
{
    public function testHttpUrlPasses(): void
    {
        $rule = new AnyUrl();

        $this->assertNull($rule->validate('https://example.com', 'link'));
    }

    public function testFtpUrlPasses(): void
    {
        $rule = new AnyUrl();

        $this->assertNull($rule->validate('ftp://example.com/file.txt', 'link'));
    }

    public function testFileUrlPasses(): void
    {
        $rule = new AnyUrl();

        $this->assertNull($rule->validate('file:///etc/passwd', 'link'));
    }

    public function testInvalidUrlFails(): void
    {
        $rule = new AnyUrl();

        $error = $rule->validate('not-a-url', 'link');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('The link field must be a valid URL.', $error->message);
    }

    public function testNonStringSkipped(): void
    {
        $rule = new AnyUrl();

        $this->assertNull($rule->validate(42, 'link'));
    }
}
