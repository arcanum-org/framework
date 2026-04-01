<?php

declare(strict_types=1);

namespace Arcanum\Toolkit\Encryption;

/**
 * Symmetric encryption contract.
 *
 * Implementations must produce authenticated ciphertext — decryption
 * must verify integrity before returning plaintext.
 */
interface Encryptor
{
    /**
     * Encrypt plaintext into an opaque, self-contained envelope.
     */
    public function encrypt(string $plaintext): string;

    /**
     * Decrypt an envelope back to plaintext.
     *
     * @throws DecryptionFailed On any failure (tampered data, wrong key, malformed envelope).
     */
    public function decrypt(string $ciphertext): string;
}
