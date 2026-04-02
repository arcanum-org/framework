<?php

declare(strict_types=1);

namespace Arcanum\Shodo\Helpers;

use Arcanum\Session\ActiveSession;

/**
 * Template helper for HTML-specific utilities.
 *
 * Usage in templates:
 *   {{! Html::csrf() !}}
 *   {{ Html::csrfToken() }}
 *   {{ Html::nonce() }}
 *   {{ Html::classIf($active, 'selected') }}
 */
final class HtmlHelper
{
    public function __construct(
        private readonly ActiveSession $session,
    ) {
    }

    /**
     * Generate a hidden CSRF token input field.
     */
    public function csrf(): string
    {
        $token = $this->session->get()->csrfToken();

        return '<input type="hidden" name="_token" value="' . $token . '">';
    }

    /**
     * Return the raw CSRF token string.
     */
    public function csrfToken(): string
    {
        return (string) $this->session->get()->csrfToken();
    }

    /**
     * Generate a random CSP nonce.
     */
    public function nonce(): string
    {
        return base64_encode(random_bytes(16));
    }

    /**
     * Return a CSS class name if the condition is true, empty string otherwise.
     */
    public function classIf(bool $condition, string $class): string
    {
        return $condition ? $class : '';
    }
}
