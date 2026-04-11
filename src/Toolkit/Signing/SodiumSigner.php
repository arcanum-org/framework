<?php

declare(strict_types=1);

namespace Arcanum\Toolkit\Signing;

/**
 * HMAC signing using libsodium's `crypto_auth` (HMAC-SHA512/256).
 *
 * Verification uses `sodium_crypto_auth_verify`, which is constant-time.
 * The signature is hex-encoded for safe transport in URLs, headers, and cookies.
 */
final class SodiumSigner implements Signer
{
    private readonly string $key;

    /**
     * @param string $key A key of exactly SODIUM_CRYPTO_AUTH_KEYBYTES (32) bytes.
     */
    public function __construct(string $key)
    {
        if (strlen($key) !== SODIUM_CRYPTO_AUTH_KEYBYTES) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Signing key must be exactly %d bytes, got %d.',
                    SODIUM_CRYPTO_AUTH_KEYBYTES,
                    strlen($key),
                )
            );
        }

        $this->key = $key;
    }

    public function sign(string $payload): string
    {
        return bin2hex(sodium_crypto_auth($payload, $this->key));
    }

    public function verify(string $payload, string $signature): bool
    {
        $mac = @hex2bin($signature);

        if ($mac === false || strlen($mac) !== SODIUM_CRYPTO_AUTH_BYTES) {
            return false;
        }

        return sodium_crypto_auth_verify($mac, $payload, $this->key);
    }
}
