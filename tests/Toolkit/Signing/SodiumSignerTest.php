<?php

declare(strict_types=1);

namespace Arcanum\Test\Toolkit\Signing;

use Arcanum\Toolkit\Signing\SodiumSigner;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SodiumSigner::class)]
final class SodiumSignerTest extends TestCase
{
    private function makeKey(): string
    {
        return random_bytes(SODIUM_CRYPTO_AUTH_KEYBYTES);
    }

    public function testSignVerifyRoundTrip(): void
    {
        $signer = new SodiumSigner($this->makeKey());
        $payload = 'Hello, Arcanum!';

        $signature = $signer->sign($payload);

        $this->assertTrue($signer->verify($payload, $signature));
    }

    public function testTamperedPayloadFailsVerification(): void
    {
        $signer = new SodiumSigner($this->makeKey());

        $signature = $signer->sign('original');

        $this->assertFalse($signer->verify('tampered', $signature));
    }

    public function testWrongKeyFailsVerification(): void
    {
        $signer1 = new SodiumSigner($this->makeKey());
        $signer2 = new SodiumSigner($this->makeKey());

        $signature = $signer1->sign('payload');

        $this->assertFalse($signer2->verify('payload', $signature));
    }

    public function testEmptyPayloadSignsAndVerifies(): void
    {
        $signer = new SodiumSigner($this->makeKey());

        $signature = $signer->sign('');

        $this->assertTrue($signer->verify('', $signature));
    }

    public function testSignatureIs64CharacterHexString(): void
    {
        $signer = new SodiumSigner($this->makeKey());

        $signature = $signer->sign('test');

        $this->assertSame(SODIUM_CRYPTO_AUTH_BYTES * 2, strlen($signature));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $signature);
    }

    public function testMalformedSignatureReturnsFalse(): void
    {
        $signer = new SodiumSigner($this->makeKey());

        $this->assertFalse($signer->verify('payload', 'not-hex'));
        $this->assertFalse($signer->verify('payload', ''));
        $this->assertFalse($signer->verify('payload', 'abcd'));
    }

    public function testConstructorRejectsWrongKeyLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SodiumSigner('too-short');
    }
}
