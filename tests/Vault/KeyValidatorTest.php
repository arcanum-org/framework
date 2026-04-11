<?php

declare(strict_types=1);

namespace Arcanum\Test\Vault;

use Arcanum\Vault\InvalidArgument;
use Arcanum\Vault\KeyValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(KeyValidator::class)]
#[UsesClass(InvalidArgument::class)]
final class KeyValidatorTest extends TestCase
{
    public function testValidKeyPasses(): void
    {
        $this->expectNotToPerformAssertions();

        KeyValidator::validate('user.profile.123');
    }

    public function testAlphanumericKeyPasses(): void
    {
        $this->expectNotToPerformAssertions();

        KeyValidator::validate('abc123');
    }

    public function testKeyWithDotsAndDashesPasses(): void
    {
        $this->expectNotToPerformAssertions();

        KeyValidator::validate('cache-key.with_underscores');
    }

    public function testEmptyKeyRejected(): void
    {
        $this->expectException(InvalidArgument::class);

        KeyValidator::validate('');
    }

    public function testKeyWithCurlyBracesRejected(): void
    {
        $this->expectException(InvalidArgument::class);

        KeyValidator::validate('key{bad}');
    }

    public function testKeyWithParenthesesRejected(): void
    {
        $this->expectException(InvalidArgument::class);

        KeyValidator::validate('key(bad)');
    }

    public function testKeyWithSlashRejected(): void
    {
        $this->expectException(InvalidArgument::class);

        KeyValidator::validate('key/bad');
    }

    public function testKeyWithBackslashRejected(): void
    {
        $this->expectException(InvalidArgument::class);

        KeyValidator::validate('key\\bad');
    }

    public function testKeyWithAtSignRejected(): void
    {
        $this->expectException(InvalidArgument::class);

        KeyValidator::validate('key@bad');
    }

    public function testKeyWithColonRejected(): void
    {
        $this->expectException(InvalidArgument::class);

        KeyValidator::validate('key:bad');
    }

    public function testImplementsPsrInterface(): void
    {
        $this->expectException(\Psr\SimpleCache\InvalidArgumentException::class);

        KeyValidator::validate('');
    }

    public function testValidateMultiplePassesForValidKeys(): void
    {
        $this->expectNotToPerformAssertions();

        KeyValidator::validateMultiple(['a', 'b', 'c']);
    }

    public function testValidateMultipleRejectsInvalidKey(): void
    {
        $this->expectException(InvalidArgument::class);

        KeyValidator::validateMultiple(['good', 'bad{key}', 'also-good']);
    }
}
