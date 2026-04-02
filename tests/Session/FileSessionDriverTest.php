<?php

declare(strict_types=1);

namespace Arcanum\Test\Session;

use Arcanum\Parchment\FileSystem;
use Arcanum\Parchment\Reader;
use Arcanum\Parchment\Writer;
use Arcanum\Session\FileSessionDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileSessionDriver::class)]
#[UsesClass(FileSystem::class)]
#[UsesClass(Reader::class)]
#[UsesClass(Writer::class)]
final class FileSessionDriverTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/arcanum_session_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->directory)) {
            $files = glob($this->directory . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
            rmdir($this->directory);
        }
    }

    public function testWriteAndReadRoundTrip(): void
    {
        $driver = new FileSessionDriver($this->directory);

        $driver->write('session-1', ['_csrf' => 'token', '_identity' => 'user-1'], 3600);

        $data = $driver->read('session-1');

        $this->assertSame('token', $data['_csrf']);
        $this->assertSame('user-1', $data['_identity']);
    }

    public function testReadReturnsEmptyArrayForMissingSession(): void
    {
        $driver = new FileSessionDriver($this->directory);

        $this->assertSame([], $driver->read('nonexistent'));
    }

    public function testReadReturnsEmptyArrayForExpiredSession(): void
    {
        $driver = new FileSessionDriver($this->directory);

        // Write with 1-second TTL then manually expire it.
        $path = $this->directory . '/expired-id.session';
        $data = serialize(['data' => ['_csrf' => 'x'], 'expiry' => time() - 1]);
        file_put_contents($path, $data);

        $this->assertSame([], $driver->read('expired-id'));
        $this->assertFileDoesNotExist($path);
    }

    public function testDestroyRemovesSessionFile(): void
    {
        $driver = new FileSessionDriver($this->directory);

        $driver->write('to-destroy', ['_csrf' => 'x'], 3600);
        $driver->destroy('to-destroy');

        $this->assertSame([], $driver->read('to-destroy'));
    }

    public function testDestroyNonexistentSessionDoesNotError(): void
    {
        $driver = new FileSessionDriver($this->directory);

        $driver->destroy('does-not-exist');

        $this->addToAssertionCount(1); // No exception thrown.
    }

    public function testGcRemovesExpiredSessions(): void
    {
        $driver = new FileSessionDriver($this->directory);

        // Write one expired, one valid.
        $expiredPath = $this->directory . '/expired.session';
        file_put_contents($expiredPath, serialize(['data' => [], 'expiry' => time() - 100]));

        $driver->write('valid', ['_csrf' => 'ok'], 3600);

        $driver->gc(3600);

        $this->assertFileDoesNotExist($expiredPath);
        $this->assertNotSame([], $driver->read('valid'));
    }

    public function testReadDeletesCorruptedFile(): void
    {
        $driver = new FileSessionDriver($this->directory);

        $path = $this->directory . '/corrupt.session';
        file_put_contents($path, 'not-serialized-data');

        $this->assertSame([], $driver->read('corrupt'));
        $this->assertFileDoesNotExist($path);
    }

    public function testCreatesDirectoryIfMissing(): void
    {
        $nested = $this->directory . '/nested/sessions';
        $driver = new FileSessionDriver($nested);

        $driver->write('test', ['_csrf' => 'x'], 3600);

        $this->assertDirectoryExists($nested);
        $this->assertNotSame([], $driver->read('test'));

        // Cleanup nested.
        unlink($nested . '/test.session');
        rmdir($nested);
        rmdir($this->directory . '/nested');
    }
}
