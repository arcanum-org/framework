<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Command;

use Arcanum\Rune\Command\MakeKeyCommand;
use Arcanum\Rune\ConsoleOutput;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Toolkit\Encryption\EncryptionKey;
use Arcanum\Toolkit\Random;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MakeKeyCommand::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(ExitCode::class)]
#[UsesClass(Input::class)]
#[UsesClass(Random::class)]
#[UsesClass(EncryptionKey::class)]
#[UsesClass(\Arcanum\Parchment\Reader::class)]
#[UsesClass(\Arcanum\Parchment\Writer::class)]
#[UsesClass(\Arcanum\Parchment\FileSystem::class)]
final class MakeKeyCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/arcanum_makekey_test_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $envFile = $this->tempDir . '/.env';
        if (file_exists($envFile)) {
            unlink($envFile);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    public function testOutputMatchesAppKeyFormat(): void
    {
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $command = new MakeKeyCommand(rootDirectory: $this->tempDir);

        $input = new Input('make:key');

        $exitCode = $command->execute($input, $output);

        $rendered = $this->readStream($stdout);
        $this->assertSame(ExitCode::Success->value, $exitCode);
        $this->assertMatchesRegularExpression('/^APP_KEY=base64:[A-Za-z0-9+\/=]+$/', trim($rendered));
    }

    public function testGeneratedKeyDecodesTo32Bytes(): void
    {
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $command = new MakeKeyCommand(rootDirectory: $this->tempDir);

        $input = new Input('make:key');
        $command->execute($input, $output);

        $rendered = trim($this->readStream($stdout));
        $base64 = substr($rendered, strlen('APP_KEY=base64:'));

        $key = EncryptionKey::fromBase64($base64);
        $this->assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($key->bytes));
    }

    public function testWriteFlagCreatesEnvFile(): void
    {
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $command = new MakeKeyCommand(rootDirectory: $this->tempDir);

        $input = new Input('make:key', flags: ['write' => true]);

        $exitCode = $command->execute($input, $output);

        $this->assertSame(ExitCode::Success->value, $exitCode);
        $this->assertFileExists($this->tempDir . '/.env');
        $contents = file_get_contents($this->tempDir . '/.env');
        $this->assertIsString($contents);
        $this->assertStringStartsWith('APP_KEY=base64:', $contents);
    }

    public function testWriteFlagReplacesExistingAppKey(): void
    {
        file_put_contents($this->tempDir . '/.env', "DB_HOST=localhost\nAPP_KEY=base64:oldkey\nAPP_DEBUG=true\n");

        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $command = new MakeKeyCommand(rootDirectory: $this->tempDir);

        $input = new Input('make:key', flags: ['write' => true]);
        $command->execute($input, $output);

        $contents = file_get_contents($this->tempDir . '/.env');
        $this->assertIsString($contents);

        $this->assertStringContainsString('DB_HOST=localhost', $contents);
        $this->assertStringContainsString('APP_DEBUG=true', $contents);
        $this->assertStringNotContainsString('oldkey', $contents);
        $this->assertMatchesRegularExpression('/APP_KEY=base64:[A-Za-z0-9+\/=]+/', $contents);
    }

    public function testWriteFlagAppendsWhenNoExistingAppKey(): void
    {
        file_put_contents($this->tempDir . '/.env', "DB_HOST=localhost\n");

        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $command = new MakeKeyCommand(rootDirectory: $this->tempDir);

        $input = new Input('make:key', flags: ['write' => true]);
        $command->execute($input, $output);

        $contents = file_get_contents($this->tempDir . '/.env');
        $this->assertIsString($contents);

        $this->assertStringContainsString('DB_HOST=localhost', $contents);
        $this->assertMatchesRegularExpression('/APP_KEY=base64:[A-Za-z0-9+\/=]+/', $contents);
    }

    /**
     * @return resource
     */
    private function createStream(): mixed
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function readStream(mixed $stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);
        $this->assertIsString($contents);
        return $contents;
    }
}
