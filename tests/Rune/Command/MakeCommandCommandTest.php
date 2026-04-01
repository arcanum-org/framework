<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Command;

use Arcanum\Rune\Command\Generator;
use Arcanum\Rune\Command\MakeCommandCommand;
use Arcanum\Rune\ConsoleOutput;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Shodo\TemplateCompiler;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(MakeCommandCommand::class)]
#[CoversClass(Generator::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(ExitCode::class)]
#[UsesClass(Input::class)]
#[UsesClass(TemplateCompiler::class)]
#[UsesClass(Strings::class)]
#[UsesClass(\Arcanum\Parchment\Reader::class)]
#[UsesClass(\Arcanum\Parchment\Writer::class)]
#[UsesClass(\Arcanum\Parchment\FileSystem::class)]
final class MakeCommandCommandTest extends TestCase
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

    public function testGeneratesCommandDtoAndHandler(): void
    {
        $command = new MakeCommandCommand($this->tempDir, 'App\\Domain');
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);

        $exitCode = $command->execute(new Input('make:command', ['Contact/Submit']), $output);

        $this->assertSame(ExitCode::Success->value, $exitCode);

        $dtoPath = $this->tempDir . '/app/Domain/Contact/Command/Submit.php';
        $handlerPath = $this->tempDir . '/app/Domain/Contact/Command/SubmitHandler.php';

        $this->assertFileExists($dtoPath);
        $this->assertFileExists($handlerPath);

        $dtoContent = (string) file_get_contents($dtoPath);
        $this->assertStringContainsString('namespace App\\Domain\\Contact\\Command;', $dtoContent);
        $this->assertStringContainsString('final class Submit', $dtoContent);

        $handlerContent = (string) file_get_contents($handlerPath);
        $this->assertStringContainsString('final class SubmitHandler', $handlerContent);
        $this->assertStringContainsString('Submit $command', $handlerContent);
        $this->assertStringContainsString('): void', $handlerContent);
    }

    public function testRefusesToOverwriteExistingFiles(): void
    {
        $command = new MakeCommandCommand($this->tempDir, 'App\\Domain');
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);

        $command->execute(new Input('make:command', ['Contact/Submit']), $output);
        $exitCode = $command->execute(new Input('make:command', ['Contact/Submit']), $output);

        $this->assertSame(ExitCode::Failure->value, $exitCode);
        $this->assertStringContainsString('already exists', $this->readStream($stderr));
    }

    public function testCreatesIntermediateDirectories(): void
    {
        $command = new MakeCommandCommand($this->tempDir, 'App\\Domain');
        $output = new ConsoleOutput($this->createStream(), $this->createStream(), ansi: false);

        $command->execute(new Input('make:command', ['Admin/Users/BanUser']), $output);

        $this->assertFileExists($this->tempDir . '/app/Domain/Admin/Users/Command/BanUser.php');
        $this->assertFileExists($this->tempDir . '/app/Domain/Admin/Users/Command/BanUserHandler.php');
    }

    public function testSingleSegmentName(): void
    {
        $command = new MakeCommandCommand($this->tempDir, 'App\\Domain');
        $output = new ConsoleOutput($this->createStream(), $this->createStream(), ansi: false);

        $command->execute(new Input('make:command', ['Submit']), $output);

        $this->assertFileExists($this->tempDir . '/app/Domain/Command/Submit.php');
        $dto = (string) file_get_contents($this->tempDir . '/app/Domain/Command/Submit.php');
        $this->assertStringContainsString('namespace App\\Domain\\Command;', $dto);
    }

    public function testEmptyNameFails(): void
    {
        $command = new MakeCommandCommand($this->tempDir, 'App\\Domain');
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);

        $exitCode = $command->execute(new Input('make:command'), $output);

        $this->assertSame(ExitCode::Invalid->value, $exitCode);
    }

    public function testInvalidCharactersFails(): void
    {
        $command = new MakeCommandCommand($this->tempDir, 'App\\Domain');
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);

        $exitCode = $command->execute(new Input('make:command', ['Bad Name!']), $output);

        $this->assertSame(ExitCode::Invalid->value, $exitCode);
        $this->assertStringContainsString('Invalid name', $this->readStream($stderr));
    }

    public function testUsesAppStubOverrideWhenPresent(): void
    {
        $stubDir = $this->tempDir . '/stubs';
        mkdir($stubDir, 0777, true);

        $dtoStub = "<?php\n// custom stub\nnamespace {{! \$namespace !}};\n"
            . "final class {{! \$className !}} {}\n";
        file_put_contents($stubDir . '/command.stub', $dtoStub);

        $handlerStub = "<?php\nnamespace {{! \$namespace !}};\n"
            . "final class {{! \$className !}}Handler {}\n";
        file_put_contents($stubDir . '/command_handler.stub', $handlerStub);

        $command = new MakeCommandCommand($this->tempDir, 'App\\Domain');
        $output = new ConsoleOutput($this->createStream(), $this->createStream(), ansi: false);

        $command->execute(new Input('make:command', ['Custom/Thing']), $output);

        $dtoContent = (string) file_get_contents($this->tempDir . '/app/Domain/Custom/Command/Thing.php');
        $this->assertStringContainsString('// custom stub', $dtoContent);
    }

    public function testPrintsCreatedPaths(): void
    {
        $command = new MakeCommandCommand($this->tempDir, 'App\\Domain');
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);

        $command->execute(new Input('make:command', ['Contact/Submit']), $output);

        $rendered = $this->readStream($stdout);
        $this->assertStringContainsString('Created:', $rendered);
        $this->assertStringContainsString('Submit.php', $rendered);
        $this->assertStringContainsString('SubmitHandler.php', $rendered);
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
