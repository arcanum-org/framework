<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Command;

use Arcanum\Rune\Command\Generator;
use Arcanum\Rune\Command\MakeQueryCommand;
use Arcanum\Rune\ConsoleOutput;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MakeQueryCommand::class)]
#[CoversClass(Generator::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(ExitCode::class)]
#[UsesClass(Input::class)]
#[UsesClass(TemplateCompiler::class)]
#[UsesClass(Strings::class)]
#[UsesClass(\Arcanum\Parchment\Reader::class)]
#[UsesClass(\Arcanum\Parchment\Writer::class)]
#[UsesClass(\Arcanum\Parchment\FileSystem::class)]
final class MakeQueryCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/arcanum_gen_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->tempDir);
    }

    public function testGeneratesQueryDtoAndHandler(): void
    {
        $command = new MakeQueryCommand($this->tempDir, 'App\\Domain');
        $output = new ConsoleOutput($this->createStream(), $this->createStream(), ansi: false);

        $exitCode = $command->execute(new Input('make:query', ['Users/Find']), $output);

        $this->assertSame(ExitCode::Success->value, $exitCode);

        $dtoPath = $this->tempDir . '/app/Domain/Users/Query/Find.php';
        $handlerPath = $this->tempDir . '/app/Domain/Users/Query/FindHandler.php';

        $this->assertFileExists($dtoPath);
        $this->assertFileExists($handlerPath);

        $dtoContent = (string) file_get_contents($dtoPath);
        $this->assertStringContainsString('namespace App\\Domain\\Users\\Query;', $dtoContent);
        $this->assertStringContainsString('final class Find', $dtoContent);

        $handlerContent = (string) file_get_contents($handlerPath);
        $this->assertStringContainsString('final class FindHandler', $handlerContent);
        $this->assertStringContainsString('Find $query', $handlerContent);
        $this->assertStringContainsString('): array', $handlerContent);
        $this->assertStringContainsString('return [];', $handlerContent);
    }

    public function testRefusesToOverwrite(): void
    {
        $command = new MakeQueryCommand($this->tempDir, 'App\\Domain');
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);

        $command->execute(new Input('make:query', ['Users/Find']), $output);
        $exitCode = $command->execute(new Input('make:query', ['Users/Find']), $output);

        $this->assertSame(ExitCode::Failure->value, $exitCode);
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

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = glob($dir . '/{,.}*', GLOB_BRACE) ?: [];
        foreach ($items as $item) {
            if (basename($item) === '.' || basename($item) === '..') {
                continue;
            }
            is_dir($item) ? $this->cleanDir($item) : @unlink($item);
        }
        @rmdir($dir);
    }
}
