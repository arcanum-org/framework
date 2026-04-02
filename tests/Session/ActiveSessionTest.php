<?php

declare(strict_types=1);

namespace Arcanum\Test\Session;

use Arcanum\Session\CsrfToken;
use Arcanum\Session\Flash;
use Arcanum\Session\Session;
use Arcanum\Session\SessionId;
use Arcanum\Session\ActiveSession;
use Arcanum\Toolkit\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ActiveSession::class)]
#[UsesClass(Session::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(CsrfToken::class)]
#[UsesClass(Flash::class)]
#[UsesClass(Random::class)]
final class ActiveSessionTest extends TestCase
{
    public function testGetThrowsWithoutSession(): void
    {
        $registry = new ActiveSession();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No active session');
        $registry->get();
    }

    public function testHasReturnsFalseInitially(): void
    {
        $registry = new ActiveSession();

        $this->assertFalse($registry->has());
    }

    public function testSetAndGetRoundTrip(): void
    {
        $registry = new ActiveSession();
        $session = new Session(SessionId::generate());

        $registry->set($session);

        $this->assertTrue($registry->has());
        $this->assertSame($session, $registry->get());
    }
}
