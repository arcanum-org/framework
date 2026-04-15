<?php

declare(strict_types=1);

namespace Arcanum\Test\Auth;

use Arcanum\Auth\Identity;
use Arcanum\Auth\IdentityProvider;
use Arcanum\Auth\SimpleIdentity;
use Arcanum\Auth\TokenGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(TokenGuard::class)]
#[UsesClass(SimpleIdentity::class)]
final class TokenGuardTest extends TestCase
{
    private function stubRequest(string $authorization = ''): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->willReturnCallback(fn(string $name) => match ($name) {
                'Authorization' => $authorization,
                default => '',
            });
        return $request;
    }

    private function stubProvider(Identity|null $returnValue = null): IdentityProvider
    {
        return new class ($returnValue) implements IdentityProvider {
            public function __construct(private readonly Identity|null $identity)
            {
            }

            public function findById(string $id): Identity|null
            {
                return null;
            }

            public function findByToken(string $token): Identity|null
            {
                return $this->identity;
            }

            public function findByCredentials(string ...$credentials): Identity|null
            {
                return null;
            }
        };
    }

    public function testResolvesIdentityFromBearerToken(): void
    {
        $guard = new TokenGuard(
            $this->stubProvider(new SimpleIdentity('user-from-token', ['api'])),
        );

        $identity = $guard->resolve($this->stubRequest('Bearer valid-token-123'));

        $this->assertNotNull($identity);
        $this->assertSame('user-from-token', $identity->id());
    }

    public function testReturnsNullWhenNoAuthorizationHeader(): void
    {
        $guard = new TokenGuard($this->stubProvider(new SimpleIdentity('x')));

        $this->assertNull($guard->resolve($this->stubRequest()));
    }

    public function testReturnsNullWhenNotBearerScheme(): void
    {
        $guard = new TokenGuard($this->stubProvider(new SimpleIdentity('x')));

        $this->assertNull($guard->resolve($this->stubRequest('Basic dXNlcjpwYXNz')));
    }

    public function testReturnsNullWhenBearerTokenIsEmpty(): void
    {
        $guard = new TokenGuard($this->stubProvider(new SimpleIdentity('x')));

        $this->assertNull($guard->resolve($this->stubRequest('Bearer ')));
    }

    public function testReturnsNullWhenProviderReturnsNull(): void
    {
        $guard = new TokenGuard($this->stubProvider(null));

        $this->assertNull($guard->resolve($this->stubRequest('Bearer expired-token')));
    }
}
