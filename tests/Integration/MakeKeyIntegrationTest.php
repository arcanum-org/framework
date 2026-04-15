<?php

declare(strict_types=1);

namespace Arcanum\Test\Integration;

use Arcanum\Cabinet\Container;
use Arcanum\Ignition\Kernel;
use Arcanum\Ignition\RuneKernel;
use Arcanum\Rune\ExitCode;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * Integration test for the make:key chicken-and-egg fix.
 *
 * Verifies that `make:key` works without APP_KEY set, that `--write`
 * persists the key, and that a normal command still gets the full
 * bootstrap chain (including Security, which throws on missing APP_KEY).
 */
#[CoversNothing]
final class MakeKeyIntegrationTest extends TestCase
{
    private string $tempDir;
    private mixed $originalArgv;
    private mixed $originalAppKey;

    protected function setUp(): void
    {
        $this->originalArgv = $_SERVER['argv'] ?? null;
        $this->originalAppKey = $_ENV['APP_KEY'] ?? null;

        // Clear APP_KEY so tests start with a clean environment.
        unset($_ENV['APP_KEY']);
        putenv('APP_KEY');

        // Create a minimal app directory structure.
        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arcanum_test_' . bin2hex(random_bytes(4));
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'config', 0755, true);
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'cache', 0755, true);

        // Minimal config — CliRouting needs app.namespace.
        file_put_contents(
            $this->tempDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php',
            "<?php\nreturn ['namespace' => 'App', 'name' => 'Test'];\n",
        );
    }

    protected function tearDown(): void
    {
        $_SERVER['argv'] = $this->originalArgv;

        // Dotenv sets $_ENV globally — restore the original APP_KEY state.
        if ($this->originalAppKey === null) {
            unset($_ENV['APP_KEY']);
            putenv('APP_KEY');
        } else {
            $_ENV['APP_KEY'] = $this->originalAppKey;
        }

        $this->removeDirectory($this->tempDir);
    }

    public function testMakeKeyWithNoAppKeyProducesValidKey(): void
    {
        // Arrange — no .env file, no APP_KEY anywhere
        $_SERVER['argv'] = ['bin/arcanum', 'make:key'];

        $stdout = fopen('php://memory', 'r+');
        $this->assertIsResource($stdout);

        $container = new Container();
        $kernel = new RuneKernel($this->tempDir);
        $container->instance(Kernel::class, $kernel);

        // Bind a memory-backed Output so we can capture stdout.
        $container->factory(\Arcanum\Rune\Output::class, function () use ($stdout) {
            $stderr = fopen('php://memory', 'r+');
            assert(is_resource($stderr));
            return new \Arcanum\Rune\ConsoleOutput($stdout, $stderr);
        });

        // Act
        $kernel->bootstrap($container);
        $exitCode = $kernel->handle(['bin/arcanum', 'make:key']);

        // Assert
        $this->assertSame(ExitCode::Success->value, $exitCode);

        rewind($stdout);
        $output = stream_get_contents($stdout);
        $this->assertIsString($output);
        $this->assertStringStartsWith('APP_KEY=base64:', trim($output));

        // Verify the key decodes to the right length (SODIUM_CRYPTO_SECRETBOX_KEYBYTES = 32).
        $encoded = str_replace('APP_KEY=base64:', '', trim($output));
        $decoded = base64_decode($encoded, true);
        $this->assertIsString($decoded);
        $this->assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($decoded));
    }

    public function testMakeKeyWriteFlagPersistsToEnvFile(): void
    {
        // Arrange — create an empty .env file
        $envPath = $this->tempDir . DIRECTORY_SEPARATOR . '.env';
        file_put_contents($envPath, "APP_DEBUG=true\n");

        $_SERVER['argv'] = ['bin/arcanum', 'make:key'];

        $stdout = fopen('php://memory', 'r+');
        $this->assertIsResource($stdout);

        $container = new Container();
        $kernel = new RuneKernel($this->tempDir);
        $container->instance(Kernel::class, $kernel);

        $container->factory(\Arcanum\Rune\Output::class, function () use ($stdout) {
            $stderr = fopen('php://memory', 'r+');
            assert(is_resource($stderr));
            return new \Arcanum\Rune\ConsoleOutput($stdout, $stderr);
        });

        // Act
        $kernel->bootstrap($container);
        $exitCode = $kernel->handle(['bin/arcanum', 'make:key', '--write']);

        // Assert
        $this->assertSame(ExitCode::Success->value, $exitCode);

        $envContents = file_get_contents($envPath);
        $this->assertIsString($envContents);
        $this->assertStringContainsString('APP_KEY=base64:', $envContents);
        $this->assertStringContainsString('APP_DEBUG=true', $envContents);
    }

    public function testNormalCommandFailsWithoutAppKey(): void
    {
        // Arrange — simulate a normal command that gets full bootstrap
        $_SERVER['argv'] = ['bin/arcanum', 'migrate'];

        $container = new Container();
        $kernel = new RuneKernel($this->tempDir);
        $container->instance(Kernel::class, $kernel);

        // Act & Assert — Security bootstrapper throws on missing APP_KEY
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_KEY is missing');

        $kernel->bootstrap($container);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}
