<?php

declare(strict_types=1);

namespace Arcanum\Test\Auth;

use Arcanum\Auth\ActiveIdentity;
use Arcanum\Auth\CliAuthResolver;
use Arcanum\Auth\CliSession;
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
    public function testResolvesFromTokenOption(): void
    {
        $active = new ActiveIdentity();
        $resolver = new CliAuthResolver(
            $active,
            fn(string $token) => new SimpleIdentity('user-from-token'),
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
            fn(string $token) => new SimpleIdentity('env-user'),
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
            fn(string $token) => new SimpleIdentity($token === 'option-token' ? 'from-option' : 'from-env'),
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
            fn(string $token) => new SimpleIdentity('should-not-be-called'),
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

    public function testResolverReturningNullLeavesActiveIdentityEmpty(): void
    {
        $active = new ActiveIdentity();
        $resolver = new CliAuthResolver(
            $active,
            fn(string $token) => null,
        );

        $resolver->resolve(new Input('command:test', options: ['token' => 'invalid']));

        $this->assertFalse($active->has());
    }

    public function testStoredSessionResolvesIdentity(): void
    {
        // Arrange
        $active = new ActiveIdentity();
        $session = $this->createMock(CliSession::class);
        $session->method('load')->willReturn('user-42');

        $resolver = new CliAuthResolver(
            activeIdentity: $active,
            tokenResolver: fn(string $token) => null,
            session: $session,
            identityResolver: fn(string $id) => new SimpleIdentity($id),
        );

        $originalEnv = getenv('ARCANUM_TOKEN');
        putenv('ARCANUM_TOKEN');

        try {
            // Act
            $resolver->resolve(new Input('command:test'));

            // Assert
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
        // Arrange
        $active = new ActiveIdentity();
        $session = $this->createMock(CliSession::class);
        $session->method('load')->willReturn(null);

        $resolver = new CliAuthResolver(
            activeIdentity: $active,
            tokenResolver: fn(string $token) => new SimpleIdentity('from-env'),
            session: $session,
            identityResolver: fn(string $id) => new SimpleIdentity($id),
        );

        $originalEnv = getenv('ARCANUM_TOKEN');
        putenv('ARCANUM_TOKEN=env-token');

        try {
            // Act
            $resolver->resolve(new Input('command:test'));

            // Assert
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
        // Arrange
        $active = new ActiveIdentity();
        $session = $this->createMock(CliSession::class);
        $session->expects($this->never())->method('load');

        $resolver = new CliAuthResolver(
            activeIdentity: $active,
            tokenResolver: fn(string $token) => new SimpleIdentity('from-token'),
            session: $session,
            identityResolver: fn(string $id) => new SimpleIdentity('from-session'),
        );

        // Act
        $resolver->resolve(new Input('command:test', options: ['token' => 'my-token']));

        // Assert
        $this->assertSame('from-token', $active->get()->id());
    }
}
