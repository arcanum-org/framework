<?php

declare(strict_types=1);

namespace Arcanum\Auth;

/**
 * The domain's representation of "who is making this request."
 *
 * Transport-agnostic — handlers receive this interface via the container
 * and never know whether it came from a session cookie, JWT, API key,
 * or CLI --token flag.
 *
 * Apps implement this interface with richer data (name, email, permissions)
 * once a persistence layer exists. SimpleIdentity covers the common case.
 */
interface Identity
{
    /**
     * Unique identifier for this identity (user ID, API key ID, etc.).
     */
    public function id(): string;

    /**
     * Roles assigned to this identity.
     *
     * @return list<string>
     */
    public function roles(): array;
}
