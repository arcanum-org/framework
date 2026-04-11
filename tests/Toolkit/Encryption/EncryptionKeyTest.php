<?php

declare(strict_types=1);

namespace Arcanum\Test\Toolkit\Encryption;

use Arcanum\Toolkit\Encryption\EncryptionKey;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(EncryptionKey::class)]
final class EncryptionKeyTest extends TestCase
{
    public function testAcceptsValid32ByteKey(): void
    {
        $bytes = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

        $key = new EncryptionKey($bytes);

        $this->assertSame($bytes, $key->bytes);
    }

    public function testRejectsTooShortKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EncryptionKey(random_bytes(16));
    }

    public function testRejectsTooLongKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EncryptionKey(random_bytes(64));
    }

    public function testRejectsEmptyKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new EncryptionKey('');
    }

    public function testFromBase64DecodesCorrectly(): void
    {
        $bytes = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $encoded = base64_encode($bytes);

        $key = EncryptionKey::fromBase64($encoded);

        $this->assertSame($bytes, $key->bytes);
    }

    public function testFromBase64RejectsInvalidBase64(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EncryptionKey::fromBase64('not-valid-base64!!!');
    }

    public function testFromBase64RejectsWrongLengthAfterDecoding(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        EncryptionKey::fromBase64(base64_encode('too-short'));
    }
}
