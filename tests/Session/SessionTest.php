<?php

declare(strict_types=1);

namespace Arcanum\Test\Session;

use Arcanum\Session\CsrfToken;
use Arcanum\Session\Flash;
use Arcanum\Session\Session;
use Arcanum\Session\SessionId;
use Arcanum\Toolkit\Random;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Session::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(CsrfToken::class)]
#[UsesClass(Flash::class)]
#[UsesClass(Random::class)]
final class SessionTest extends TestCase
{
    public function testNewSessionHasProvidedId(): void
    {
        $id = SessionId::generate();
        $session = new Session($id);

        $this->assertSame($id, $session->id());
    }

    public function testNewSessionGeneratesCsrfToken(): void
    {
        $session = new Session(SessionId::generate());

        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $session->csrfToken()->value);
    }

    public function testRestoresCsrfTokenFromData(): void
    {
        $token = str_repeat('ab', 32);
        $session = new Session(SessionId::generate(), ['_csrf' => $token]);

        $this->assertSame($token, $session->csrfToken()->value);
    }

    public function testRotateCsrfTokenGeneratesNewToken(): void
    {
        $session = new Session(SessionId::generate());
        $original = $session->csrfToken()->value;

        $session->rotateCsrfToken();

        $this->assertNotSame($original, $session->csrfToken()->value);
    }

    public function testFlashAccessor(): void
    {
        $session = new Session(SessionId::generate(), ['_flash' => ['msg' => 'hello']]);

        $this->assertSame('hello', $session->flash()->get('msg'));
    }

    public function testSetIdentityStoresIdAndRegenerates(): void
    {
        $originalId = SessionId::generate();
        $session = new Session($originalId);

        $session->setIdentity('user-42');

        $this->assertSame('user-42', $session->identityId());
        $this->assertTrue($session->wasRegenerated());
        $this->assertNotSame($originalId->value, $session->id()->value);
    }

    public function testClearIdentityInvalidatesSession(): void
    {
        $session = new Session(SessionId::generate(), ['_identity' => 'user-42']);

        $session->clearIdentity();

        $this->assertSame('', $session->identityId());
        $this->assertTrue($session->wasInvalidated());
    }

    public function testIdentityIdRestoredFromData(): void
    {
        $session = new Session(SessionId::generate(), ['_identity' => 'user-99']);

        $this->assertSame('user-99', $session->identityId());
    }

    public function testIdentityIdEmptyByDefault(): void
    {
        $session = new Session(SessionId::generate());

        $this->assertSame('', $session->identityId());
    }

    public function testRegenerateChangesIdAndSetsFlag(): void
    {
        $originalId = SessionId::generate();
        $session = new Session($originalId);

        $this->assertFalse($session->wasRegenerated());

        $session->regenerate();

        $this->assertTrue($session->wasRegenerated());
        $this->assertNotSame($originalId->value, $session->id()->value);
    }

    public function testInvalidateClearsEverything(): void
    {
        $session = new Session(SessionId::generate(), [
            '_identity' => 'user-1',
            '_csrf' => str_repeat('ab', 32),
            '_flash' => ['msg' => 'hi'],
        ]);

        $originalCsrf = $session->csrfToken()->value;
        $session->invalidate();

        $this->assertTrue($session->wasInvalidated());
        $this->assertSame('', $session->identityId());
        $this->assertNotSame($originalCsrf, $session->csrfToken()->value);
        $this->assertSame([], $session->flash()->all());
    }

    public function testToArraySerializesState(): void
    {
        $session = new Session(SessionId::generate());
        $session->flash()->set('notice', 'Done.');

        $data = $session->toArray();

        $this->assertArrayHasKey('_csrf', $data);
        $this->assertArrayHasKey('_flash', $data);
        $this->assertArrayHasKey('_identity', $data);
        $this->assertSame(['notice' => 'Done.'], $data['_flash']);
        $this->assertSame('', $data['_identity']);
    }

    public function testToArrayRoundTrips(): void
    {
        $session = new Session(SessionId::generate());
        $session->setIdentity('user-5');
        $session->flash()->set('msg', 'Saved.');

        $data = $session->toArray();
        $restored = new Session(SessionId::generate(), $data);

        $this->assertSame($session->csrfToken()->value, $restored->csrfToken()->value);
        $this->assertSame('user-5', $restored->identityId());
        $this->assertSame('Saved.', $restored->flash()->get('msg'));
    }
}
