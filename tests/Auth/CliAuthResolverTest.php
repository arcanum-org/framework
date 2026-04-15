<?php

declare(strict_types=1);

namespace Arcanum\Test\Auth;

use Arcanum\Auth\ActiveIdentity;
use Arcanum\Auth\CliAuthResolver;
use Arcanum\Auth\CliSession;
use Arcanum\Auth\Identity;
use Arcanum\Auth\IdentityProvider;
use Arcanum\Auth\SimpleIdentity;
use Arcanum\Rune\Input;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CliAuthResolver::class)]
#[UsesClass(ActiveIdentity::class)]
#[UsesClass(SimpleIdentity::class)]
#[UsesClass(Input::class)]
final class CliAuthResolverTest extends TestCase
{
    /**
     * @param (\Closure(string): (Identity|null))|null $byToken
     * @param (\Closure(string): (Identity|null))|null $byId
     */
    private function stubProvider(
        \Closure|null $byToken = null,
        \Closure|null $byId = null,
    ): IdentityProvider {
        return new class ($byToken, $byId) implements IdentityProvider {
            /**
             * @param (\Closure(string): (Identity|null))|null $byToken
             * @param (\Closure(string): (Identity|null))|null $byId
             */
            public function __construct(
                private readonly \Closure|null $byToken,
                private readonly \Closure|null $byId,
            ) {
            }

            public function findById(string $id): Identity|null
            {
                return $this->byId !== null ? ($this->byId)($id) : null;
            }

            public function findByToken(string $token): Identity|null
            {
                return $this->byToken !== null ? ($this->byToken)($token) : null;
            }

            public function findByCredentials(string ...$credentials): Identity|null
            {
                return null;
            }
        };
    }

    public function testResolvesFromTokenOption(): void
    {
        $active = new ActiveIdentity();
        $resolver = new CliAuthResolver(
            $active,
            $this->stubProvider(byToken: fn(string $token) => new SimpleIdentity('user-from-token')),
        );

        $resolver->resolve(new Input('command:test', options: ['token' => 'my-secret']));

        $this->assertTrue($active->has());
        $this->assertSame('user-from-token', $active->get()->id());
    }

    public function testResolvesFromEnvironmentVariable(): void
    {
        $active = new ActiveIdentity();
        $resolver = new CliAuthResolver(
            $active,
            $this->stubProvider(byToken: fn(string $token) => new SimpleIdentity('env-user')),
        );

        $originalEnv = getenv('ARCANUM_TOKEN');
        putenv('ARCANUM_TOKEN=env-token-value');

        try {
            $resolver->resolve(new Input('command:test'));

            $this->assertTrue($active->has());
            $this->assertSame('env-user', $active->get()->id());
        } finally {
            if ($originalEnv === false) {
                putenv('ARCANUM_TOKEN');
            } else {
                putenv('ARCANUM_TOKEN=' . $originalEnv);
            }
        }
    }

    public function testTokenOptionTakesPrecedenceOverEnv(): void
    {
        $active = new ActiveIdentity();
        $resolver = new CliAuthResolver(
            $active,
            $this->stubProvider(
                byToken: fn(string $token) => new SimpleIdentity(
                    $token === 'option-token' ? 'from-option' : 'from-env',
                ),
            ),
        );

        $originalEnv = getenv('ARCANUM_TOKEN');
        putenv('ARCANUM_TOKEN=env-token');

        try {
            $resolver->resolve(new Input('command:test', options: ['token' => 'option-token']));

            $this->assertSame('from-option', $active->get()->id());
        } finally {
            if ($originalEnv === false) {
                putenv('ARCANUM_TOKEN');
            } else {
                putenv('ARCANUM_TOKEN=' . $originalEnv);
            }
        }
    }

    public function testNoTokenLeavesActiveIdentityEmpty(): void
    {
        $active = new ActiveIdentity();
        $resolver = new CliAuthResolver(
            $active,
            $this->stubProvider(byToken: fn(string $token) => new SimpleIdentity('should-not-be-called')),
        );

        $originalEnv = getenv('ARCANUM_TOKEN');
        putenv('ARCANUM_TOKEN');

        try {
            $resolver->resolve(new Input('command:test'));

            $this->assertFalse($active->has());
        } finally {
            if ($originalEnv !== false) {
                putenv('ARCANUM_TOKEN=' . $originalEnv);
            }
        }
    }

    public function testProviderReturningNullLeavesActiveIdentityEmpty(): void
    {
        $active = new ActiveIdentity();
        $resolver = new CliAuthResolver(
            $active,
            $this->stubProvider(byToken: fn(string $token) => null),
        );

        $resolver->resolve(new Input('command:test', options: ['token' => 'invalid']));

        $this->assertFalse($active->has());
    }

    public function testStoredSessionResolvesIdentity(): void
    {
        $active = new ActiveIdentity();
        $session = $this->createStub(CliSession::class);
        $session->method('load')->willReturn('user-42');

        $resolver = new CliAuthResolver(
            activeIdentity: $active,
            provider: $this->stubProvider(byId: fn(string $id) => new SimpleIdentity($id)),
            session: $session,
        );

        $originalEnv = getenv('ARCANUM_TOKEN');
        putenv('ARCANUM_TOKEN');

        try {
            $resolver->resolve(new Input('command:test'));

            $this->assertTrue($active->has());
            $this->assertSame('user-42', $active->get()->id());
        } finally {
            if ($originalEnv !== false) {
                putenv('ARCANUM_TOKEN=' . $originalEnv);
            }
        }
    }

    public function testExpiredSessionFallsThroughToEnv(): void
    {
        $active = new ActiveIdentity();
        $session = $this->createStub(CliSession::class);
        $session->method('load')->willReturn(null);

        $resolver = new CliAuthResolver(
            activeIdentity: $active,
            provider: $this->stubProvider(
                byToken: fn(string $token) => new SimpleIdentity('from-env'),
                byId: fn(string $id) => new SimpleIdentity($id),
            ),
            session: $session,
        );

        $originalEnv = getenv('ARCANUM_TOKEN');
        putenv('ARCANUM_TOKEN=env-token');

        try {
            $resolver->resolve(new Input('command:test'));

            $this->assertTrue($active->has());
            $this->assertSame('from-env', $active->get()->id());
        } finally {
            if ($originalEnv === false) {
                putenv('ARCANUM_TOKEN');
            } else {
                putenv('ARCANUM_TOKEN=' . $originalEnv);
            }
        }
    }

    public function testTokenOverridesStoredSession(): void
    {
        $active = new ActiveIdentity();
        $session = $this->createMock(CliSession::class);
        $session->expects($this->never())->method('load');

        $resolver = new CliAuthResolver(
            activeIdentity: $active,
            provider: $this->stubProvider(
                byToken: fn(string $token) => new SimpleIdentity('from-token'),
                byId: fn(string $id) => new SimpleIdentity('from-session'),
            ),
            session: $session,
        );

        $resolver->resolve(new Input('command:test', options: ['token' => 'my-token']));

        $this->assertSame('from-token', $active->get()->id());
    }
}
