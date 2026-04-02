<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Command;

use Arcanum\Auth\CliSession;
use Arcanum\Rune\Command\LogoutCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(LogoutCommand::class)]
#[UsesClass(Input::class)]
final class LogoutCommandTest extends TestCase
{
    public function testClearsSessionAndPrintsMessage(): void
    {
        // Arrange
        $session = $this->createMock(CliSession::class);
        $session->expects($this->once())->method('clear');

        $output = $this->createMock(Output::class);
        $output->expects($this->once())
            ->method('writeLine')
            ->with('Logged out.');

        $command = new LogoutCommand(session: $session);

        // Act
        $exit = $command->execute(
            Input::fromArgv(['arcanum', 'logout']),
            $output,
        );

        // Assert
        $this->assertSame(ExitCode::Success->value, $exit);
    }
}
