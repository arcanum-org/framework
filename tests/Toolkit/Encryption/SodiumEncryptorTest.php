<?php

declare(strict_types=1);

namespace Arcanum\Test\Toolkit\Encryption;

use Arcanum\Toolkit\Encryption\DecryptionFailed;
use Arcanum\Toolkit\Encryption\EncryptionKey;
use Arcanum\Toolkit\Encryption\SodiumEncryptor;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(SodiumEncryptor::class)]
#[UsesClass(EncryptionKey::class)]
#[UsesClass(DecryptionFailed::class)]
final class SodiumEncryptorTest extends TestCase
{
    private function makeEncryptor(): SodiumEncryptor
    {
        $key = new EncryptionKey(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        return new SodiumEncryptor($key);
    }

    private function makeKey(): EncryptionKey
    {
        return new EncryptionKey(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $encryptor = $this->makeEncryptor();
        $plaintext = 'Hello, Arcanum!';

        $ciphertext = $encryptor->encrypt($plaintext);
        $decrypted = $encryptor->decrypt($ciphertext);

        $this->assertSame($plaintext, $decrypted);
    }

    public function testEmptyStringEncryptsAndDecrypts(): void
    {
        $encryptor = $this->makeEncryptor();

        $ciphertext = $encryptor->encrypt('');
        $decrypted = $encryptor->decrypt($ciphertext);

        $this->assertSame('', $decrypted);
    }

    public function testBinaryDataRoundTrips(): void
    {
        $encryptor = $this->makeEncryptor();
        $binary = random_bytes(256);

        $ciphertext = $encryptor->encrypt($binary);
        $decrypted = $encryptor->decrypt($ciphertext);

        $this->assertSame($binary, $decrypted);
    }

    public function testTwoEncryptionsProduceDifferentEnvelopes(): void
    {
        $encryptor = $this->makeEncryptor();
        $plaintext = 'same plaintext';

        $first = $encryptor->encrypt($plaintext);
        $second = $encryptor->encrypt($plaintext);

        $this->assertNotSame($first, $second);
    }

    public function testTamperedCiphertextThrowsDecryptionFailed(): void
    {
        $encryptor = $this->makeEncryptor();
        $ciphertext = $encryptor->encrypt('secret');

        // Flip a byte in the middle of the envelope.
        $decoded = (string) base64_decode($ciphertext, true);
        $bytes = str_split($decoded);
        $offset = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + 2;
        $bytes[$offset] = chr(ord($bytes[$offset]) ^ 0xFF);
        $tampered = base64_encode(implode('', $bytes));

        $this->expectException(DecryptionFailed::class);
        $encryptor->decrypt($tampered);
    }

    public function testWrongKeyThrowsDecryptionFailed(): void
    {
        $key1 = $this->makeKey();
        $key2 = $this->makeKey();

        $encryptor1 = new SodiumEncryptor($key1);
        $encryptor2 = new SodiumEncryptor($key2);

        $ciphertext = $encryptor1->encrypt('secret');

        $this->expectException(DecryptionFailed::class);
        $encryptor2->decrypt($ciphertext);
    }

    public function testMalformedEnvelopeTruncatedThrowsDecryptionFailed(): void
    {
        $encryptor = $this->makeEncryptor();

        $this->expectException(DecryptionFailed::class);
        $encryptor->decrypt(base64_encode('short'));
    }

    public function testMalformedEnvelopeEmptyThrowsDecryptionFailed(): void
    {
        $encryptor = $this->makeEncryptor();

        $this->expectException(DecryptionFailed::class);
        $encryptor->decrypt('');
    }

    public function testMalformedEnvelopeNotBase64ThrowsDecryptionFailed(): void
    {
        $encryptor = $this->makeEncryptor();

        $this->expectException(DecryptionFailed::class);
        $encryptor->decrypt('not-valid-base64!!!');
    }
}
