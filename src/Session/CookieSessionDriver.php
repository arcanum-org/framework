<?php

declare(strict_types=1);

namespace Arcanum\Session;

use Arcanum\Toolkit\Encryption\DecryptionFailed;
use Arcanum\Toolkit\Encryption\Encryptor;

/**
 * Encrypted client-side session driver.
 *
 * All session data is encrypted and stored in the cookie itself.
 * No server-side storage required. Size-limited by cookie constraints
 * (~4KB), but sufficient for the structured session data (CSRF token,
 * identity ID, flash messages).
 *
 * Uses the framework's Encryptor (SodiumEncryptor by default) for
 * authenticated encryption — any tampering is detected and the
 * session is silently discarded.
 */
final class CookieSessionDriver implements SessionDriver
{
    /**
     * In-memory buffer for the current request.
     *
     * Cookie writes happen via the middleware (Set-Cookie header),
     * not here. This driver buffers data so the middleware can
     * encrypt and attach it to the response.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $pending = [];

    public function __construct(private readonly Encryptor $encryptor)
    {
    }

    public function read(string $id): array
    {
        // Cookie driver reads from pending buffer (written by middleware
        // after decrypting the inbound cookie).
        return $this->pending[$id] ?? [];
    }

    public function write(string $id, array $data, int $ttl): void
    {
        $this->pending[$id] = $data;
    }

    public function destroy(string $id): void
    {
        unset($this->pending[$id]);
    }

    public function gc(int $maxLifetime): void
    {
        // Client-side storage — no server-side garbage to collect.
    }

    /**
     * Decrypt a cookie value into session data and load it into the buffer.
     *
     * @return array<string, mixed>
     */
    public function decryptCookie(string $id, string $cookieValue): array
    {
        try {
            $json = $this->encryptor->decrypt($cookieValue);
            $data = json_decode($json, true);

            if (!is_array($data)) {
                return [];
            }

            /** @var array<string, mixed> $data */
            $this->pending[$id] = $data;
            return $data;
        } catch (DecryptionFailed) {
            return [];
        }
    }

    /**
     * Encrypt the buffered session data for cookie storage.
     */
    public function encryptForCookie(string $id): string
    {
        $data = $this->pending[$id] ?? [];
        return $this->encryptor->encrypt(json_encode($data, JSON_THROW_ON_ERROR));
    }
}
