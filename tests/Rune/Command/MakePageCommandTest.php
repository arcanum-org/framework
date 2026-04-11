<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Command;

use Arcanum\Rune\Command\Generator;
use Arcanum\Rune\Command\MakePageCommand;
use Arcanum\Rune\ConsoleOutput;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MakePageCommand::class)]
#[CoversClass(Generator::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(ExitCode::class)]
#[UsesClass(Input::class)]
#[UsesClass(TemplateCompiler::class)]
#[UsesClass(Strings::class)]
#[UsesClass(\Arcanum\Parchment\Reader::class)]
#[UsesClass(\Arcanum\Parchment\Writer::class)]
#[UsesClass(\Arcanum\Parchment\FileSystem::class)]
final class MakePageCommandTest extends TestCase
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

    public function testGeneratesPageDtoAndTemplate(): void
    {
        $command = new MakePageCommand($this->tempDir, 'App');
        $output = new ConsoleOutput($this->createStream(), $this->createStream(), ansi: false);

        $exitCode = $command->execute(new Input('make:page', ['About']), $output);

        $this->assertSame(ExitCode::Success->value, $exitCode);

        $dtoPath = $this->tempDir . '/app/Pages/About.php';
        $templatePath = $this->tempDir . '/app/Pages/About.html';

        $this->assertFileExists($dtoPath);
        $this->assertFileExists($templatePath);

        $dtoContent = (string) file_get_contents($dtoPath);
        $this->assertStringContainsString('namespace App\\Pages;', $dtoContent);
        $this->assertStringContainsString('final class About', $dtoContent);
        $this->assertStringContainsString("'About'", $dtoContent);

        $templateContent = (string) file_get_contents($templatePath);
        $this->assertStringContainsString('{{ $title }}', $templateContent);
        $this->assertStringContainsString('<!DOCTYPE html>', $templateContent);
    }

    public function testNestedPageHasCorrectNamespace(): void
    {
        $command = new MakePageCommand($this->tempDir, 'App');
        $output = new ConsoleOutput($this->createStream(), $this->createStream(), ansi: false);

        $command->execute(new Input('make:page', ['Docs/GettingStarted']), $output);

        $dtoPath = $this->tempDir . '/app/Pages/Docs/GettingStarted.php';
        $this->assertFileExists($dtoPath);

        $content = (string) file_get_contents($dtoPath);
        $this->assertStringContainsString('namespace App\\Pages\\Docs;', $content);
        $this->assertStringContainsString('final class GettingStarted', $content);
        $this->assertStringContainsString("'Getting Started'", $content);
    }

    public function testCustomPagesNamespace(): void
    {
        $command = new MakePageCommand(
            $this->tempDir,
            'App',
            pagesNamespace: 'App\\Views',
            pagesDirectory: 'app/Views',
        );
        $output = new ConsoleOutput($this->createStream(), $this->createStream(), ansi: false);

        $command->execute(new Input('make:page', ['Contact']), $output);

        $dtoPath = $this->tempDir . '/app/Views/Contact.php';
        $this->assertFileExists($dtoPath);
        $this->assertStringContainsString('namespace App\\Views;', (string) file_get_contents($dtoPath));
    }

    public function testRefusesToOverwrite(): void
    {
        $command = new MakePageCommand($this->tempDir, 'App');
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);

        $command->execute(new Input('make:page', ['About']), $output);
        $exitCode = $command->execute(new Input('make:page', ['About']), $output);

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
