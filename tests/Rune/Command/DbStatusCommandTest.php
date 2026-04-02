<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Command;

use Arcanum\Forge\ConnectionFactory;
use Arcanum\Forge\PdoConnection;
use Arcanum\Forge\ConnectionManager;
use Arcanum\Gather\Configuration;
use Arcanum\Gather\Registry;
use Arcanum\Rune\Command\DbStatusCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(DbStatusCommand::class)]
#[UsesClass(PdoConnection::class)]
#[UsesClass(ConnectionFactory::class)]
#[UsesClass(ConnectionManager::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(Registry::class)]
#[UsesClass(\Arcanum\Forge\Result::class)]
#[UsesClass(\Arcanum\Forge\Sql::class)]
final class DbStatusCommandTest extends TestCase
{
    private string $domainRoot;

    protected function setUp(): void
    {
        $this->domainRoot = sys_get_temp_dir() . '/arcanum_dbstatus_test_' . uniqid();
        mkdir($this->domainRoot . '/Shop/Model', 0777, true);
        file_put_contents($this->domainRoot . '/Shop/Model/AllProducts.sql', 'SELECT 1');
        file_put_contents($this->domainRoot . '/Shop/Model/GetById.sql', 'SELECT 1');
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->domainRoot);
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = glob($dir . '/*') ?: [];
        foreach ($items as $item) {
            is_dir($item) ? $this->cleanDir($item) : @unlink($item);
        }
        @rmdir($dir);
    }

    public function testShowsConnectionInfo(): void
    {
        // Arrange
        $manager = new ConnectionManager(
            defaultConnection: 'main',
            connections: [
                'main' => ['driver' => 'sqlite', 'database' => ':memory:'],
            ],
            factory: new ConnectionFactory(),
        );

        $output = $this->createStub(Output::class);
        $lines = [];
        $output->method('writeLine')->willReturnCallback(
            function (string $line) use (&$lines) {
                $lines[] = $line;
            },
        );

        $command = new DbStatusCommand(
            connections: $manager,
            domainRoot: $this->domainRoot,
        );

        // Act
        $exit = $command->execute(
            Input::fromArgv(['arcanum', 'db:status']),
            $output,
        );

        // Assert
        $this->assertSame(ExitCode::Success->value, $exit);

        $text = implode("\n", $lines);
        $this->assertStringContainsString('Default connection: main', $text);
        $this->assertStringContainsString('sqlite', $text);
        $this->assertStringContainsString('OK', $text);
        $this->assertStringContainsString('Shop', $text);
        $this->assertStringContainsString('2 SQL file(s)', $text);
    }

    public function testReportsConnectionErrorGracefully(): void
    {
        // Arrange — bad connection that will fail on connect
        $manager = new ConnectionManager(
            defaultConnection: 'bad',
            connections: [
                'bad' => ['driver' => 'mysql', 'host' => '0.0.0.0', 'port' => 1, 'database' => 'x'],
            ],
            factory: new ConnectionFactory(),
        );

        $output = $this->createStub(Output::class);
        $lines = [];
        $output->method('writeLine')->willReturnCallback(
            function (string $line) use (&$lines) {
                $lines[] = $line;
            },
        );

        $command = new DbStatusCommand(connections: $manager);

        // Act
        $exit = $command->execute(
            Input::fromArgv(['arcanum', 'db:status']),
            $output,
        );

        // Assert — should not crash, should report FAILED
        $this->assertSame(ExitCode::Success->value, $exit);

        $text = implode("\n", $lines);
        $this->assertStringContainsString('FAILED', $text);
    }
}
