<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Command;

use Arcanum\Rune\Command\CacheStatusCommand;
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

#[CoversClass(CacheStatusCommand::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(ExitCode::class)]
#[UsesClass(Input::class)]
#[UsesClass(CacheManager::class)]
#[UsesClass(ArrayDriver::class)]
#[UsesClass(KeyValidator::class)]
#[UsesClass(InvalidArgument::class)]
final class CacheStatusCommandTest extends TestCase
{
    public function testShowsConfiguredStores(): void
    {
        $manager = new CacheManager(
            defaultStore: 'array',
            stores: [
                'array' => ['driver' => 'array'],
                'null' => ['driver' => 'null'],
            ],
        );

        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $command = new CacheStatusCommand(cacheManager: $manager);

        $exitCode = $command->execute(new Input('cache:status'), $output);

        $rendered = $this->readStream($stdout);
        $this->assertSame(ExitCode::Success->value, $exitCode);
        $this->assertStringContainsString('Default store: array', $rendered);
        $this->assertStringContainsString('array', $rendered);
        $this->assertStringContainsString('null', $rendered);
        $this->assertStringContainsString('(default)', $rendered);
    }

    public function testShowsFrameworkStoreAssignments(): void
    {
        $manager = new CacheManager(
            defaultStore: 'array',
            stores: ['array' => ['driver' => 'array']],
            frameworkStores: ['pages' => 'array', 'middleware' => 'array'],
        );

        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $command = new CacheStatusCommand(cacheManager: $manager);

        $command->execute(new Input('cache:status'), $output);

        $rendered = $this->readStream($stdout);
        $this->assertStringContainsString('Framework store assignments:', $rendered);
        $this->assertStringContainsString('pages', $rendered);
        $this->assertStringContainsString('middleware', $rendered);
    }

    public function testFailsWithNoCacheManager(): void
    {
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);
        $command = new CacheStatusCommand();

        $exitCode = $command->execute(new Input('cache:status'), $output);

        $this->assertSame(ExitCode::Failure->value, $exitCode);
        $this->assertStringContainsString('not available', $this->readStream($stderr));
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
