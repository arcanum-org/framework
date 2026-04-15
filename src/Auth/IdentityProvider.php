<?php

declare(strict_types=1);

namespace Arcanum\Auth;

/**
 * The app's bridge between the auth system and user storage.
 *
 * Guards call these methods to resolve an Identity from whatever
 * the transport provides — a session ID, a Bearer token, or login
 * credentials. The implementation decides where to look (database,
 * cache, config file) and what constitutes a valid identity.
 *
 * Every method returns null for "not found" or "invalid." Normal
 * lookup failures (unknown ID, expired token, wrong password) are
 * not exceptional — return null and let the guard handle it.
 * Only throw for infrastructure failures (database down, etc.).
 */
interface IdentityProvider
{
    /**
     * Look up an identity by its unique identifier.
     *
     * Used by SessionGuard (session stores the ID after login)
     * and CliAuthResolver (CLI session stores the ID after `login`).
     */
    public function findById(string $id): Identity|null;

    /**
     * Look up an identity by a Bearer token or API key.
     *
     * Used by TokenGuard (Authorization header) and CliAuthResolver
     * (--token flag or ARCANUM_TOKEN env var). The implementation
     * handles token validation, expiry, and revocation.
     */
    public function findByToken(string $token): Identity|null;

    /**
     * Look up an identity by login credentials.
     *
     * Used by the login flow. The arguments are whatever the app's
     * login form collects — typically username/email and password,
     * but the variadic signature supports any credential shape.
     *
     * The implementation is responsible for password verification.
     */
    public function findByCredentials(string ...$credentials): Identity|null;
}
