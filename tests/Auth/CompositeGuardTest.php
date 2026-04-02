<?php

declare(strict_types=1);

namespace Arcanum\Test\Auth;

use Arcanum\Auth\CompositeGuard;
use Arcanum\Auth\Guard;
use Arcanum\Auth\SimpleIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(CompositeGuard::class)]
#[UsesClass(SimpleIdentity::class)]
final class CompositeGuardTest extends TestCase
{
    private function stubRequest(): ServerRequestInterface
    {
        return $this->createStub(ServerRequestInterface::class);
    }

    private function nullGuard(): Guard
    {
        $guard = $this->createStub(Guard::class);
        $guard->method('resolve')->willReturn(null);
        return $guard;
    }

    private function identityGuard(string $id): Guard
    {
        $guard = $this->createStub(Guard::class);
        $guard->method('resolve')->willReturn(new SimpleIdentity($id));
        return $guard;
    }

    public function testFirstGuardWins(): void
    {
        $composite = new CompositeGuard(
            $this->identityGuard('first'),
            $this->identityGuard('second'),
        );

        $identity = $composite->resolve($this->stubRequest());

        $this->assertNotNull($identity);
        $this->assertSame('first', $identity->id());
    }

    public function testFallsBackToSecondGuard(): void
    {
        $composite = new CompositeGuard(
            $this->nullGuard(),
            $this->identityGuard('second'),
        );

        $identity = $composite->resolve($this->stubRequest());

        $this->assertNotNull($identity);
        $this->assertSame('second', $identity->id());
    }

    public function testReturnsNullWhenAllGuardsReturnNull(): void
    {
        $composite = new CompositeGuard(
            $this->nullGuard(),
            $this->nullGuard(),
        );

        $this->assertNull($composite->resolve($this->stubRequest()));
    }
}
