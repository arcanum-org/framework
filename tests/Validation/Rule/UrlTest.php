<?php

declare(strict_types=1);

namespace Arcanum\Test\Validation\Rule;

use Arcanum\Validation\Rule\Url;
use Arcanum\Validation\ValidationError;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Url::class)]
#[UsesClass(ValidationError::class)]
final class UrlTest extends TestCase
{
    public function testValidHttpUrlPasses(): void
    {
        $rule = new Url();

        $this->assertNull($rule->validate('https://example.com', 'website'));
    }

    public function testValidUrlWithPathPasses(): void
    {
        $rule = new Url();

        $this->assertNull($rule->validate('https://example.com/path?q=1', 'website'));
    }

    public function testInvalidUrlFails(): void
    {
        $rule = new Url();

        $error = $rule->validate('not-a-url', 'website');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('website', $error->field);
        $this->assertSame('The website field must be a valid URL.', $error->message);
    }

    public function testEmptyStringFails(): void
    {
        $rule = new Url();

        $this->assertInstanceOf(ValidationError::class, $rule->validate('', 'website'));
    }

    public function testNonStringSkipped(): void
    {
        $rule = new Url();

        $this->assertNull($rule->validate(42, 'website'));
    }

    public function testHttpUrlPasses(): void
    {
        $rule = new Url();

        $this->assertNull($rule->validate('http://example.com', 'website'));
    }

    public function testFtpUrlRejected(): void
    {
        $rule = new Url();

        $error = $rule->validate('ftp://example.com/file.txt', 'website');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('The website field must use http or https.', $error->message);
    }

    public function testFileUrlRejected(): void
    {
        $rule = new Url();

        $error = $rule->validate('file:///etc/passwd', 'website');

        $this->assertInstanceOf(ValidationError::class, $error);
        $this->assertSame('The website field must use http or https.', $error->message);
    }
}
