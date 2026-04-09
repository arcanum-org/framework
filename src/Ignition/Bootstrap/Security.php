<?php

declare(strict_types=1);

namespace Arcanum\Ignition\Bootstrap;

use Arcanum\Cabinet\Application;
use Arcanum\Gather\Environment;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Toolkit\Encryption\Encryptor;
use Arcanum\Toolkit\Encryption\EncryptionKey;
use Arcanum\Toolkit\Encryption\SodiumEncryptor;
use Arcanum\Toolkit\Hashing\BcryptHasher;
use Arcanum\Toolkit\Hashing\Hasher;
use Arcanum\Toolkit\Signing\Signer;
use Arcanum\Toolkit\Signing\SodiumSigner;

/**
 * Registers security services (encryption, signing, hashing) in the container.
 *
 * Reads `APP_KEY` from the environment, decodes it, and wires up:
 * - `Encryptor` → `SodiumEncryptor` (XSalsa20-Poly1305)
 * - `Signer` → `SodiumSigner` (HMAC-SHA512/256)
 * - `Hasher` → `BcryptHasher` (apps can override to Argon2Hasher)
 *
 * Must run after `Bootstrap\Environment` and `Bootstrap\Configuration`.
 */
class Security implements Bootstrapper
{
    public function bootstrap(Application $container): void
    {
        /** @var Environment $env */
        $env = $container->get(Environment::class);

        $appKey = $env->get('APP_KEY');

        if (!is_string($appKey) || $appKey === '') {
            throw new \RuntimeException(
                'APP_KEY is missing. Generate one with: php arcanum make:key'
            );
        }

        $key = $this->parseKey($appKey);

        $container->instance(EncryptionKey::class, $key);
        $container->service(Encryptor::class, SodiumEncryptor::class);

        $container->specify(SodiumSigner::class, '$key', $key->bytes);
        $container->service(Signer::class, SodiumSigner::class);

        $container->service(Hasher::class, BcryptHasher::class);
    }

    private function parseKey(string $appKey): EncryptionKey
    {
        // Strip the `base64:` prefix if present.
        if (str_starts_with($appKey, 'base64:')) {
            return EncryptionKey::fromBase64(substr($appKey, 7));
        }

        return EncryptionKey::fromBase64($appKey);
    }
}
