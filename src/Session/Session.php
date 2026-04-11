<?php

declare(strict_types=1);

namespace Arcanum\Session;

/**
 * Structured session with purpose-built accessors.
 *
 * Sessions expose three concerns: CSRF protection, flash messages,
 * and identity persistence. There is no generic get/set API —
 * domain state belongs in the domain layer, not the session.
 */
final class Session
{
    private SessionId $id;
    private Flash $flash;
    private CsrfToken $csrfToken;
    private string $identityId;
    private bool $regenerated = false;
    private bool $invalidated = false;

    /**
     * @param array<string, mixed> $data Raw session data from the driver.
     */
    public function __construct(SessionId $id, array $data = [])
    {
        $this->id = $id;
        $raw = $data['_flash'] ?? [];
        /** @var array<string, string> $flashData */
        $flashData = is_array($raw) ? array_filter($raw, 'is_string') : [];
        $this->flash = new Flash($flashData);
        $this->csrfToken = isset($data['_csrf']) && is_string($data['_csrf'])
            ? CsrfToken::fromString($data['_csrf'])
            : CsrfToken::generate();
        $this->identityId = isset($data['_identity']) && is_string($data['_identity'])
            ? $data['_identity']
            : '';
    }

    public function id(): SessionId
    {
        return $this->id;
    }

    // ── Flash Messages ───────────────────────────────────────────

    public function flash(): Flash
    {
        return $this->flash;
    }

    // ── CSRF ─────────────────────────────────────────────────────

    public function csrfToken(): CsrfToken
    {
        return $this->csrfToken;
    }

    /**
     * Rotate the CSRF token (e.g., after login).
     */
    public function rotateCsrfToken(): void
    {
        $this->csrfToken = CsrfToken::generate();
    }

    // ── Identity ─────────────────────────────────────────────────

    /**
     * Store the authenticated user's identifier.
     *
     * Called by the auth layer after successful authentication.
     * Automatically regenerates the session ID to prevent fixation.
     */
    public function setIdentity(string $id): void
    {
        $this->identityId = $id;
        $this->regenerate();
    }

    /**
     * The stored identity identifier, or empty string if not authenticated.
     */
    public function identityId(): string
    {
        return $this->identityId;
    }

    /**
     * Clear the stored identity (logout).
     *
     * Invalidates the session entirely to prevent session reuse.
     */
    public function clearIdentity(): void
    {
        $this->identityId = '';
        $this->invalidate();
    }

    // ── Lifecycle ────────────────────────────────────────────────

    /**
     * Regenerate the session ID (preserving data).
     *
     * Prevents session fixation attacks. Called automatically
     * on login. The middleware handles destroying the old ID.
     */
    public function regenerate(): void
    {
        $this->regenerated = true;
        $this->id = SessionId::generate();
    }

    /**
     * Invalidate the session entirely (destroy data + regenerate ID).
     *
     * Used on logout. The middleware destroys the old session in the driver.
     */
    public function invalidate(): void
    {
        $this->invalidated = true;
        $this->identityId = '';
        $this->flash = new Flash();
        $this->csrfToken = CsrfToken::generate();
        $this->id = SessionId::generate();
    }

    /**
     * Whether the session ID was regenerated during this request.
     */
    public function wasRegenerated(): bool
    {
        return $this->regenerated;
    }

    /**
     * Whether the session was invalidated during this request.
     */
    public function wasInvalidated(): bool
    {
        return $this->invalidated;
    }

    /**
     * Serialize session state for the driver.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            '_csrf' => $this->csrfToken->value,
            '_flash' => $this->flash->pending(),
            '_identity' => $this->identityId,
        ];
    }
}
