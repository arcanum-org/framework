<?php

declare(strict_types=1);

namespace Arcanum\Toolkit\Encryption;

/**
 * Authenticated symmetric encryption using libsodium's XSalsa20-Poly1305.
 *
 * Wraps `sodium_crypto_secretbox` / `sodium_crypto_secretbox_open`.
 * Each encryption generates a fresh 24-byte nonce. The envelope is
 * `base64(nonce || ciphertext)` — fully self-contained.
 *
 * XSalsa20-Poly1305 was chosen over AES-256-GCM because:
 * - 24-byte nonce (vs GCM's 12-byte) makes nonce collision astronomically unlikely
 * - libsodium is built into PHP 8.x — no extension management
 * - Simpler, harder-to-misuse API
 */
final class SodiumEncryptor implements Encryptor
{
    public function __construct(
        private readonly EncryptionKey $key,
    ) {
    }

    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $this->key->bytes);

        return base64_encode($nonce . $ciphertext);
    }

    public function decrypt(string $ciphertext): string
    {
        $decoded = base64_decode($ciphertext, true);

        $minLength = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES + SODIUM_CRYPTO_SECRETBOX_MACBYTES;

        if ($decoded === false || strlen($decoded) < $minLength) {
            throw new DecryptionFailed();
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($encrypted, $nonce, $this->key->bytes);

        if ($plaintext === false) {
            throw new DecryptionFailed();
        }

        return $plaintext;
    }
}
