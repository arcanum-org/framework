<?php

declare(strict_types=1);

namespace Arcanum\Session;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware that manages the session lifecycle.
 *
 * On the way in: reads the session ID from the cookie, loads session
 * data from the driver, and registers the Session object in the container.
 *
 * On the way out: persists session data back to the driver and sets
 * the session cookie on the response.
 *
 * Handles session regeneration (new ID, same data) and invalidation
 * (new ID, empty data, old session destroyed).
 */
final class SessionMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly SessionDriver $driver,
        private readonly SessionConfig $config,
        private readonly SessionRegistry $registry,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookies = $request->getCookieParams();
        $cookieValue = $cookies[$this->config->cookieName] ?? null;

        // Resolve or generate session ID.
        $originalId = is_string($cookieValue) ? SessionId::fromString($cookieValue) : null;
        $id = $originalId ?? SessionId::generate();

        // Load session data from driver.
        $rawCookie = is_string($cookieValue) ? $cookieValue : '';
        $data = $originalId !== null ? $this->loadData($id, $rawCookie) : [];

        // Hydrate the Session object and make it available.
        $session = new Session($id, $data);
        $this->registry->set($session);

        // Probabilistic garbage collection (1% chance per request).
        if ($this->driver instanceof GarbageCollectable && random_int(1, 100) === 1) {
            $this->driver->gc(max(1, $this->config->lifetime));
        }

        // Delegate to the next middleware / handler.
        $response = $handler->handle($request);

        // Persist and attach cookie.
        return $this->finalizeSession($session, $originalId, $response);
    }

    /**
     * Load session data, handling cookie driver specially.
     *
     * @return array<string, mixed>
     */
    private function loadData(SessionId $id, string $cookieValue): array
    {
        if ($this->driver instanceof CookieSessionDriver && $cookieValue !== '') {
            return $this->driver->decryptCookie($id->value, $cookieValue);
        }

        return $this->driver->read($id->value);
    }

    private function finalizeSession(
        Session $session,
        SessionId|null $originalId,
        ResponseInterface $response,
    ): ResponseInterface {
        // If the session was invalidated, destroy the old session data.
        if ($session->wasInvalidated() && $originalId !== null) {
            $this->driver->destroy($originalId->value);
        } elseif ($session->wasRegenerated() && $originalId !== null) {
            // Regenerated — destroy old ID, keep data under new ID.
            $this->driver->destroy($originalId->value);
        }

        // Persist session data under the (possibly new) ID.
        $this->persistSession($session);

        // Set the session cookie.
        $cookieHeader = $this->buildCookieHeader($session);
        return $response->withAddedHeader('Set-Cookie', $cookieHeader);
    }

    private function persistSession(Session $session): void
    {
        $lifetime = max(1, $this->config->lifetime);

        if ($this->driver instanceof CookieSessionDriver) {
            // Cookie driver buffers data; middleware will encrypt it in buildCookieHeader.
            $this->driver->write($session->id()->value, $session->toArray(), $lifetime);
            return;
        }

        $this->driver->write($session->id()->value, $session->toArray(), $lifetime);
    }

    private function buildCookieHeader(Session $session): string
    {
        if ($this->driver instanceof CookieSessionDriver) {
            $encrypted = $this->driver->encryptForCookie($session->id()->value);
            return $this->config->cookieHeader($encrypted);
        }

        return $this->config->cookieHeader($session->id()->value);
    }
}
