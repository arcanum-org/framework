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

    public function testClearsStrayFrameworkCacheSubdirectories(): void
    {
        // Arrange — set up a fake framework cache directory with several
        // subdirectories. Some are "known" (app, templates) and handled
        // by the structured clears; others (helpers, pages) are stray
        // and should be caught by the directory walk.
        $cacheDir = sys_get_temp_dir() . '/arcanum_clearcmd_' . uniqid();
        mkdir($cacheDir, 0777, true);
        mkdir($cacheDir . '/app', 0777, true);
        mkdir($cacheDir . '/templates', 0777, true);
        mkdir($cacheDir . '/helpers', 0777, true);
        mkdir($cacheDir . '/pages', 0777, true);

        // Stray cache files
        file_put_contents($cacheDir . '/helpers/discovered.cache', 'stale');
        file_put_contents($cacheDir . '/pages/routes.cache', 'stale');
        // Known directories also have files (left intact for the walker)
        file_put_contents($cacheDir . '/templates/x.php', 'leave alone');

        try {
            $stdout = $this->createStream();
            $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
            $command = new CacheClearCommand(
                frameworkCacheDirectory: $cacheDir,
            );

            // Act
            $exitCode = $command->execute(new Input('cache:clear'), $output);

            // Assert
            $this->assertSame(ExitCode::Success->value, $exitCode);

            // Stray directories were emptied
            $this->assertFileDoesNotExist($cacheDir . '/helpers/discovered.cache');
            $this->assertFileDoesNotExist($cacheDir . '/pages/routes.cache');
            // The directories themselves remain (so file drivers don't lose their root)
            $this->assertDirectoryExists($cacheDir . '/helpers');
            $this->assertDirectoryExists($cacheDir . '/pages');

            // Known directories were skipped by the walker (the structured
            // clears handle them via TemplateCache and CacheManager)
            $this->assertFileExists($cacheDir . '/templates/x.php');

            // Output mentions the stray clears
            $rendered = $this->readStream($stdout);
            $this->assertStringContainsString('Cleared cache directory: helpers', $rendered);
            $this->assertStringContainsString('Cleared cache directory: pages', $rendered);
            $this->assertStringNotContainsString('Cleared cache directory: app', $rendered);
            $this->assertStringNotContainsString('Cleared cache directory: templates', $rendered);
        } finally {
            $this->removeDir($cacheDir);
        }
    }

    public function testStrayWalkIsNoOpWhenDirectoryDoesNotExist(): void
    {
        // Arrange — point at a directory that doesn't exist
        $bogus = sys_get_temp_dir() . '/arcanum_does_not_exist_' . uniqid();

        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $command = new CacheClearCommand(
            frameworkCacheDirectory: $bogus,
        );

        // Act
        $exitCode = $command->execute(new Input('cache:clear'), $output);

        // Assert — succeeds without throwing or printing about clears
        $this->assertSame(ExitCode::Success->value, $exitCode);
        $rendered = $this->readStream($stdout);
        $this->assertStringNotContainsString('Cleared cache directory:', $rendered);
    }

    public function testStrayWalkRecursesIntoNestedFiles(): void
    {
        // Arrange — a stray subdirectory with nested file structure
        $cacheDir = sys_get_temp_dir() . '/arcanum_clearcmd_nested_' . uniqid();
        mkdir($cacheDir . '/middleware/deep/nested', 0777, true);
        file_put_contents($cacheDir . '/middleware/top.cache', 'stale');
        file_put_contents($cacheDir . '/middleware/deep/mid.cache', 'stale');
        file_put_contents($cacheDir . '/middleware/deep/nested/leaf.cache', 'stale');

        try {
            $stdout = $this->createStream();
            $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
            $command = new CacheClearCommand(
                frameworkCacheDirectory: $cacheDir,
            );

            // Act
            $command->execute(new Input('cache:clear'), $output);

            // Assert — all nested files removed
            $this->assertFileDoesNotExist($cacheDir . '/middleware/top.cache');
            $this->assertFileDoesNotExist($cacheDir . '/middleware/deep/mid.cache');
            $this->assertFileDoesNotExist($cacheDir . '/middleware/deep/nested/leaf.cache');
            // Top-level stray dir remains, nested ones removed
            $this->assertDirectoryExists($cacheDir . '/middleware');
            $this->assertDirectoryDoesNotExist($cacheDir . '/middleware/deep');
        } finally {
            $this->removeDir($cacheDir);
        }
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            is_dir($path) ? $this->removeDir($path) : @unlink($path);
        }
        @rmdir($dir);
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
