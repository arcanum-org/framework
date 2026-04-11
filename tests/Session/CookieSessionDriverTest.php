<?php

declare(strict_types=1);

namespace Arcanum\Test\Session;

use Arcanum\Session\CookieSessionDriver;
use Arcanum\Toolkit\Encryption\DecryptionFailed;
use Arcanum\Toolkit\Encryption\Encryptor;
use Arcanum\Toolkit\Encryption\EncryptionKey;
use Arcanum\Toolkit\Encryption\SodiumEncryptor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CookieSessionDriver::class)]
#[UsesClass(SodiumEncryptor::class)]
#[UsesClass(EncryptionKey::class)]
#[UsesClass(DecryptionFailed::class)]
final class CookieSessionDriverTest extends TestCase
{
    private function encryptor(): Encryptor
    {
        return new SodiumEncryptor(new EncryptionKey(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    }

    public function testWriteAndReadFromBuffer(): void
    {
        $driver = new CookieSessionDriver($this->encryptor());

        $driver->write('sess-1', ['_csrf' => 'token'], 3600);

        $this->assertSame(['_csrf' => 'token'], $driver->read('sess-1'));
    }

    public function testReadReturnsEmptyArrayForUnknownId(): void
    {
        $driver = new CookieSessionDriver($this->encryptor());

        $this->assertSame([], $driver->read('unknown'));
    }

    public function testDestroyRemovesFromBuffer(): void
    {
        $driver = new CookieSessionDriver($this->encryptor());

        $driver->write('sess-1', ['_csrf' => 'x'], 3600);
        $driver->destroy('sess-1');

        $this->assertSame([], $driver->read('sess-1'));
    }

    public function testEncryptAndDecryptCookieRoundTrip(): void
    {
        $encryptor = $this->encryptor();
        $driver = new CookieSessionDriver($encryptor);

        $driver->write('sess-1', ['_csrf' => 'abc', '_identity' => 'user-1'], 3600);
        $encrypted = $driver->encryptForCookie('sess-1');

        // Simulate a new request with a fresh driver instance.
        $driver2 = new CookieSessionDriver($encryptor);
        $data = $driver2->decryptCookie('sess-1', $encrypted);

        $this->assertSame('abc', $data['_csrf']);
        $this->assertSame('user-1', $data['_identity']);
    }

    public function testDecryptCookieReturnEmptyOnTamperedData(): void
    {
        $driver = new CookieSessionDriver($this->encryptor());

        $data = $driver->decryptCookie('sess-1', 'tampered-garbage');

        $this->assertSame([], $data);
    }

    public function testDecryptCookieReturnEmptyOnWrongKey(): void
    {
        $encryptor1 = $this->encryptor();
        $encryptor2 = $this->encryptor();

        $driver1 = new CookieSessionDriver($encryptor1);
        $driver1->write('sess-1', ['_csrf' => 'x'], 3600);
        $encrypted = $driver1->encryptForCookie('sess-1');

        $driver2 = new CookieSessionDriver($encryptor2);
        $data = $driver2->decryptCookie('sess-1', $encrypted);

        $this->assertSame([], $data);
    }
}
