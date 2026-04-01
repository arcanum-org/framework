<?php

declare(strict_types=1);

namespace Arcanum\Toolkit\Encryption;

/**
 * Value object wrapping a raw symmetric encryption key.
 *
 * Validates that the key is exactly SODIUM_CRYPTO_SECRETBOX_KEYBYTES (32 bytes).
 * The key is stored base64-encoded in the environment as `APP_KEY=base64:<key>`.
 */
final class EncryptionKey
{
    public readonly string $bytes;

    public function __construct(string $bytes)
    {
        if (strlen($bytes) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Encryption key must be exactly %d bytes, got %d.',
                    SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
                    strlen($bytes),
                )
            );
        }

        $this->bytes = $bytes;
    }

    /**
     * Create a key from a base64-encoded string.
     *
     * Accepts the raw base64 value (without the `base64:` prefix).
     */
    public static function fromBase64(string $encoded): self
    {
        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64 encoding for encryption key.');
        }

        return new self($decoded);
    }
}
