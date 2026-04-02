<?php

declare(strict_types=1);

namespace Arcanum\Test\Auth;

use Arcanum\Auth\CliSession;
use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Toolkit\Encryption\EncryptionKey;
use Arcanum\Toolkit\Encryption\SodiumEncryptor;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CliSession::class)]
#[UsesClass(SodiumEncryptor::class)]
#[UsesClass(EncryptionKey::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Writer::class)]
#[UsesClass(FileSystem::class)]
final class CliSessionTest extends TestCase
{
    private string $path;
    private SodiumEncryptor $encryptor;

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir() . '/arcanum_clisession_test_' . uniqid();
        $key = new EncryptionKey(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
        $this->encryptor = new SodiumEncryptor($key);
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    private function session(): CliSession
    {
        return new CliSession(encryptor: $this->encryptor, path: $this->path);
    }

    public function testStoreAndLoadRoundTrip(): void
    {
        // Arrange
        $session = $this->session();

        // Act
        $session->store('user-42', 3600);
        $id = $session->load();

        // Assert
        $this->assertSame('user-42', $id);
    }

    public function testExpiredReturnsNullAndDeletesFile(): void
    {
        // Arrange — store with TTL of 0 (already expired)
        $session = $this->session();
        $session->store('user-42', 0);

        // Need to wait 1 second for time() to advance past expiry
        sleep(1);

        // Act
        $id = $session->load();

        // Assert
        $this->assertNull($id);
        $this->assertFileDoesNotExist($this->path);
    }

    public function testCorruptFileReturnsNull(): void
    {
        // Arrange — write garbage to the file
        file_put_contents($this->path, 'not-encrypted-data');

        // Act
        $id = $this->session()->load();

        // Assert
        $this->assertNull($id);
        $this->assertFileDoesNotExist($this->path);
    }

    public function testClearDeletesFile(): void
    {
        // Arrange
        $session = $this->session();
        $session->store('user-42', 3600);
        $this->assertFileExists($this->path);

        // Act
        $session->clear();

        // Assert
        $this->assertFileDoesNotExist($this->path);
    }

    public function testMissingFileReturnsNull(): void
    {
        // Act
        $id = $this->session()->load();

        // Assert
        $this->assertNull($id);
    }

    public function testClearOnMissingFileDoesNotThrow(): void
    {
        // Act & Assert — should not error
        $this->session()->clear();
        $this->assertFileDoesNotExist($this->path);
    }
}
