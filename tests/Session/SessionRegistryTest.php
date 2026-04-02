<?php

declare(strict_types=1);

namespace Arcanum\Test\Session;

use Arcanum\Session\CsrfToken;
use Arcanum\Session\Flash;
use Arcanum\Session\Session;
use Arcanum\Session\SessionId;
use Arcanum\Session\SessionRegistry;
use Arcanum\Toolkit\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionRegistry::class)]
#[UsesClass(Session::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(CsrfToken::class)]
#[UsesClass(Flash::class)]
#[UsesClass(Random::class)]
final class SessionRegistryTest extends TestCase
{
    public function testGetThrowsWithoutSession(): void
    {
        $registry = new SessionRegistry();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active session');
        $registry->get();
    }

    public function testHasReturnsFalseInitially(): void
    {
        $registry = new SessionRegistry();

        $this->assertFalse($registry->has());
    }

    public function testSetAndGetRoundTrip(): void
    {
        $registry = new SessionRegistry();
        $session = new Session(SessionId::generate());

        $registry->set($session);

        $this->assertTrue($registry->has());
        $this->assertSame($session, $registry->get());
    }
}
