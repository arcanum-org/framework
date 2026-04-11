<?php

declare(strict_types=1);

namespace Arcanum\Test\Auth;

use Arcanum\Auth\SessionGuard;
use Arcanum\Auth\SimpleIdentity;
use Arcanum\Session\ActiveSession;
use Arcanum\Session\CsrfToken;
use Arcanum\Session\Flash;
use Arcanum\Session\Session;
use Arcanum\Session\SessionId;
use Arcanum\Toolkit\Hex;
use Arcanum\Toolkit\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(SessionGuard::class)]
#[UsesClass(SimpleIdentity::class)]
#[UsesClass(ActiveSession::class)]
#[UsesClass(Session::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(CsrfToken::class)]
#[UsesClass(Flash::class)]
#[UsesClass(Random::class)]
#[UsesClass(Hex::class)]
final class SessionGuardTest extends TestCase
{
    private function stubRequest(): ServerRequestInterface
    {
        return $this->createStub(ServerRequestInterface::class);
    }

    public function testResolvesIdentityFromSession(): void
    {
        $activeSession = new ActiveSession();
        $session = new Session(SessionId::generate(), ['_identity' => 'user-5']);
        $activeSession->set($session);

        $guard = new SessionGuard(
            $activeSession,
            fn(string $id) => new SimpleIdentity($id, ['admin']),
        );

        $identity = $guard->resolve($this->stubRequest());

        $this->assertNotNull($identity);
        $this->assertSame('user-5', $identity->id());
        $this->assertSame(['admin'], $identity->roles());
    }

    public function testReturnsNullWhenSessionHasNoIdentity(): void
    {
        $activeSession = new ActiveSession();
        $activeSession->set(new Session(SessionId::generate()));

        $guard = new SessionGuard(
            $activeSession,
            fn(string $id) => new SimpleIdentity($id),
        );

        $this->assertNull($guard->resolve($this->stubRequest()));
    }

    public function testReturnsNullWhenNoActiveSession(): void
    {
        $activeSession = new ActiveSession();

        $guard = new SessionGuard(
            $activeSession,
            fn(string $id) => new SimpleIdentity($id),
        );

        $this->assertNull($guard->resolve($this->stubRequest()));
    }

    public function testReturnsNullWhenResolverReturnsNull(): void
    {
        $activeSession = new ActiveSession();
        $session = new Session(SessionId::generate(), ['_identity' => 'user-deleted']);
        $activeSession->set($session);

        $guard = new SessionGuard(
            $activeSession,
            fn(string $id) => null,
        );

        $this->assertNull($guard->resolve($this->stubRequest()));
    }
}
