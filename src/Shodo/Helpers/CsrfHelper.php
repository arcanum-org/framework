<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Helpers;

use Arcanum\Session\ActiveSession;

/**
 * Template helper for CSRF protection.
 *
 * Usage in templates:
 *   {{ csrf }}              — directive shorthand for field()
 *   {{! Csrf::field() !}}   — hidden input with CSRF token
 *   {{ Csrf::token() }}     — raw token string
 */
final class CsrfHelper
{
    public function __construct(
        private readonly ActiveSession $session,
    ) {
    }

    /**
     * Generate a hidden CSRF token input field.
     */
    public function field(): string
    {
        $token = htmlspecialchars(
            (string) $this->session->get()->csrfToken(),
            ENT_QUOTES,
            'UTF-8',
        );

        return '<input type="hidden" name="_token" value="' . $token . '">';
    }

    /**
     * Return the raw CSRF token string.
     */
    public function token(): string
    {
        return (string) $this->session->get()->csrfToken();
    }
}
