<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Command;

use Arcanum\Rune\Command\CacheClearCommand;
use Arcanum\Rune\ConsoleOutput;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Vault\ArrayDriver;
use Arcanum\Vault\CacheManager;
use Arcanum\Vault\InvalidArgument;
use Arcanum\Vault\KeyValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CacheClearCommand::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(ExitCode::class)]
#[UsesClass(Input::class)]
#[UsesClass(CacheManager::class)]
#[UsesClass(ArrayDriver::class)]
#[UsesClass(KeyValidator::class)]
#[UsesClass(InvalidArgument::class)]
final class CacheClearCommandTest extends TestCase
{
    public function testClearsAllStores(): void
    {
        $manager = new CacheManager(
            defaultStore: 'array',
            stores: ['array' => ['driver' => 'array']],
        );

        $store = $manager->store('array');
        $store->set('key', 'value');

        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $command = new CacheClearCommand(cacheManager: $manager);

        $exitCode = $command->execute(new Input('cache:clear'), $output);

        $this->assertSame(ExitCode::Success->value, $exitCode);
        $this->assertNull($store->get('key'));

        $rendered = $this->readStream($stdout);
        $this->assertStringContainsString('Cleared cache store: array', $rendered);
        $this->assertStringContainsString('All caches cleared.', $rendered);
    }

    public function testClearsSingleStoreWithFlag(): void
    {
        $manager = new CacheManager(
            defaultStore: 'array',
            stores: [
                'array' => ['driver' => 'array'],
                'null' => ['driver' => 'null'],
            ],
        );

        $store = $manager->store('array');
        $store->set('key', 'value');

        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $command = new CacheClearCommand(cacheManager: $manager);

        $input = new Input('cache:clear', options: ['store' => 'array']);
        $exitCode = $command->execute($input, $output);

        $this->assertSame(ExitCode::Success->value, $exitCode);
        $this->assertNull($store->get('key'));
        $this->assertStringContainsString('Cleared cache store: array', $this->readStream($stdout));
    }

    public function testFailsForUnknownStore(): void
    {
        $manager = new CacheManager(
            defaultStore: 'array',
            stores: ['array' => ['driver' => 'array']],
        );

        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);
        $command = new CacheClearCommand(cacheManager: $manager);

        $input = new Input('cache:clear', options: ['store' => 'nonexistent']);
        $exitCode = $command->execute($input, $output);

        $this->assertSame(ExitCode::Failure->value, $exitCode);
    }

    public function testWorksWithNoCacheManager(): void
    {
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $command = new CacheClearCommand();

        $exitCode = $command->execute(new Input('cache:clear'), $output);

        $this->assertSame(ExitCode::Success->value, $exitCode);
        $this->assertStringContainsString('All caches cleared.', $this->readStream($stdout));
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
