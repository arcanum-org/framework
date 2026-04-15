<?php

declare(strict_types=1);

namespace Arcanum\Test\Auth;

use Arcanum\Auth\Identity;
use Arcanum\Auth\IdentityProvider;
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

    private function stubProvider(Identity|null $returnValue = null): IdentityProvider
    {
        return new class ($returnValue) implements IdentityProvider {
            public function __construct(private readonly Identity|null $identity)
            {
            }

            public function findById(string $id): Identity|null
            {
                return $this->identity;
            }

            public function findByToken(string $token): Identity|null
            {
                return null;
            }

            public function findByCredentials(string ...$credentials): Identity|null
            {
                return null;
            }
        };
    }

    public function testResolvesIdentityFromSession(): void
    {
        $activeSession = new ActiveSession();
        $session = new Session(SessionId::generate(), ['_identity' => 'user-5']);
        $activeSession->set($session);

        $guard = new SessionGuard(
            $activeSession,
            $this->stubProvider(new SimpleIdentity('user-5', ['admin'])),
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
            $this->stubProvider(new SimpleIdentity('unused')),
        );

        $this->assertNull($guard->resolve($this->stubRequest()));
    }

    public function testReturnsNullWhenNoActiveSession(): void
    {
        $activeSession = new ActiveSession();

        $guard = new SessionGuard(
            $activeSession,
            $this->stubProvider(new SimpleIdentity('unused')),
        );

        $this->assertNull($guard->resolve($this->stubRequest()));
    }

    public function testReturnsNullWhenProviderReturnsNull(): void
    {
        $activeSession = new ActiveSession();
        $session = new Session(SessionId::generate(), ['_identity' => 'user-deleted']);
        $activeSession->set($session);

        $guard = new SessionGuard(
            $activeSession,
            $this->stubProvider(null),
        );

        $this->assertNull($guard->resolve($this->stubRequest()));
    }
}
