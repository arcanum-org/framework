<?php

declare(strict_types=1);

namespace Arcanum\Toolkit\Encryption;

/**
 * Thrown when decryption fails for any reason.
 *
 * Intentionally carries no detail about *why* decryption failed —
 * leaking that information would aid attackers.
 */
final class DecryptionFailed extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Decryption failed.');
    }
}
