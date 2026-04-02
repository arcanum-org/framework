<?php

declare(strict_types=1);

namespace Arcanum\Test\Auth;

use Arcanum\Auth\ActiveIdentity;
use Arcanum\Auth\Attribute\RequiresAuth;
use Arcanum\Auth\Attribute\RequiresPolicy;
use Arcanum\Auth\Attribute\RequiresRole;
use Arcanum\Auth\AuthorizationGuard;
use Arcanum\Auth\SimpleIdentity;
use Arcanum\Flow\Conveyor\Command;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use Arcanum\Ignition\Transport;
use Arcanum\Test\Fixture\Auth\AdminDto;
use Arcanum\Test\Fixture\Auth\AllowPolicy;
use Arcanum\Test\Fixture\Auth\AuthenticatedDto;
use Arcanum\Test\Fixture\Auth\DenyPolicy;
use Arcanum\Test\Fixture\Auth\DenyPolicyDto;
use Arcanum\Test\Fixture\Auth\MultiRoleDto;
use Arcanum\Test\Fixture\Auth\PolicyDto;
use Arcanum\Test\Fixture\Auth\PublicDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

#[CoversClass(AuthorizationGuard::class)]
#[UsesClass(ActiveIdentity::class)]
#[UsesClass(SimpleIdentity::class)]
#[UsesClass(RequiresAuth::class)]
#[UsesClass(RequiresRole::class)]
#[UsesClass(RequiresPolicy::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(Command::class)]
final class AuthorizationGuardTest extends TestCase
{
    private function guard(
        ActiveIdentity $active,
        Transport $transport = Transport::Http,
        ContainerInterface|null $container = null,
    ): AuthorizationGuard {
        return new AuthorizationGuard(
            $active,
            $transport,
            $container ?? $this->createStub(ContainerInterface::class),
        );
    }

    /**
     * @param list<string> $roles
     */
    private function activeWith(string $id, array $roles = []): ActiveIdentity
    {
        $active = new ActiveIdentity();
        $active->set(new SimpleIdentity($id, $roles));
        return $active;
    }

    // ── Public DTOs ──────────────────────────────────────────────

    public function testPublicDtoPassesThrough(): void
    {
        $guard = $this->guard(new ActiveIdentity());
        $called = false;

        ($guard)(new PublicDto(), function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    // ── #[RequiresAuth] ─────────────────────────────────────────

    public function testRequiresAuthPassesWithIdentity(): void
    {
        $guard = $this->guard($this->activeWith('user-1'));
        $called = false;

        ($guard)(new AuthenticatedDto(), function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function testRequiresAuthThrows401WithoutIdentity(): void
    {
        $guard = $this->guard(new ActiveIdentity());

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        ($guard)(new AuthenticatedDto(), fn() => null);
    }

    public function testRequiresAuthThrowsRuntimeExceptionOnCli(): void
    {
        $guard = $this->guard(new ActiveIdentity(), Transport::Cli);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Authentication required.');

        ($guard)(new AuthenticatedDto(), fn() => null);
    }

    // ── #[RequiresRole] ─────────────────────────────────────────

    public function testRequiresRolePassesWithMatchingRole(): void
    {
        $guard = $this->guard($this->activeWith('user-1', ['admin']));
        $called = false;

        ($guard)(new AdminDto(), function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function testRequiresRoleThrows403WithWrongRole(): void
    {
        $guard = $this->guard($this->activeWith('user-1', ['viewer']));

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ($guard)(new AdminDto(), fn() => null);
    }

    public function testRequiresRoleThrows401WithNoIdentity(): void
    {
        $guard = $this->guard(new ActiveIdentity());

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        ($guard)(new AdminDto(), fn() => null);
    }

    public function testMultiRolePassesWithAnyMatchingRole(): void
    {
        $guard = $this->guard($this->activeWith('user-1', ['moderator']));
        $called = false;

        ($guard)(new MultiRoleDto(), function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    // ── #[RequiresPolicy] ───────────────────────────────────────

    public function testPolicyPassesWhenAuthorized(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            fn(string $class) => new $class(),
        );

        $guard = $this->guard($this->activeWith('user-1'), container: $container);
        $called = false;

        ($guard)(new PolicyDto(), function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }

    public function testPolicyThrows403WhenDenied(): void
    {
        $container = $this->createStub(ContainerInterface::class);
        $container->method('get')->willReturnCallback(
            fn(string $class) => new $class(),
        );

        $guard = $this->guard($this->activeWith('user-1'), container: $container);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(403);

        ($guard)(new DenyPolicyDto(), fn() => null);
    }

    // ── HandlerProxy ────────────────────────────────────────────

    public function testHandlerProxyResolvesUnderlyingClass(): void
    {
        $guard = $this->guard(new ActiveIdentity());
        $proxy = new Command(AuthenticatedDto::class, []);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(401);

        ($guard)($proxy, fn() => null);
    }

    public function testNonExistentClassSkipped(): void
    {
        $guard = $this->guard(new ActiveIdentity());
        $proxy = new Command('NonExistent\\Class', []);
        $called = false;

        ($guard)($proxy, function () use (&$called) {
            $called = true;
        });

        $this->assertTrue($called);
    }
}
