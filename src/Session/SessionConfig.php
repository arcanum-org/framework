<?php

declare(strict_types=1);

namespace Arcanum\Session;

/**
 * Session configuration value object.
 *
 * Holds all cookie and lifetime settings. Built from config/session.php
 * by the Session bootstrapper.
 */
final class SessionConfig
{
    public function __construct(
        public readonly string $cookieName = 'arcanum_session',
        public readonly int $lifetime = 7200,
        public readonly string $path = '/',
        public readonly string $domain = '',
        public readonly bool $secure = true,
        public readonly bool $httpOnly = true,
        public readonly string $sameSite = 'Lax',
    ) {
    }

    /**
     * Build the Set-Cookie header value for a session ID.
     */
    public function cookieHeader(string $sessionId, bool $expire = false): string
    {
        $parts = [
            $this->cookieName . '=' . ($expire ? '' : $sessionId),
        ];

        if ($expire) {
            $parts[] = 'Expires=Thu, 01 Jan 1970 00:00:00 GMT';
            $parts[] = 'Max-Age=0';
        } else {
            $parts[] = 'Max-Age=' . $this->lifetime;
        }

        $parts[] = 'Path=' . $this->path;

        if ($this->domain !== '') {
            $parts[] = 'Domain=' . $this->domain;
        }

        if ($this->secure) {
            $parts[] = 'Secure';
        }

        if ($this->httpOnly) {
            $parts[] = 'HttpOnly';
        }

        if ($this->sameSite !== '') {
            $parts[] = 'SameSite=' . $this->sameSite;
        }

        return implode('; ', $parts);
    }
}
