<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Command;

use Arcanum\Auth\CliSession;
use Arcanum\Auth\Identity;
use Arcanum\Auth\SimpleIdentity;
use Arcanum\Rune\Command\LoginCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Rune\Prompter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(LoginCommand::class)]
#[UsesClass(SimpleIdentity::class)]
#[UsesClass(Input::class)]
final class LoginCommandTest extends TestCase
{
    public function testSuccessfulLoginStoresSessionAndPrintsConfirmation(): void
    {
        // Arrange
        $identity = new SimpleIdentity('user-42', ['admin']);

        $prompter = $this->createMock(Prompter::class);
        $prompter->expects($this->exactly(2))
            ->method('ask')
            ->willReturn('alice@example.com');
        $prompter->expects($this->never())->method('secret');

        $session = $this->createMock(CliSession::class);
        $session->expects($this->once())
            ->method('store')
            ->with('user-42', 3600);

        $output = $this->createMock(Output::class);
        $output->expects($this->once())
            ->method('writeLine')
            ->with($this->stringContains('user-42'));

        $command = new LoginCommand(
            prompter: $prompter,
            session: $session,
            credentialsResolver: fn(string $email, string $name) => $identity,
            fields: ['email', 'name'],
            ttl: 3600,
        );

        // Act
        $exit = $command->execute(
            Input::fromArgv(['arcanum', 'login']),
            $output,
        );

        // Assert
        $this->assertSame(ExitCode::Success->value, $exit);
    }

    public function testFailedLoginPrintsErrorWithExitCode1(): void
    {
        // Arrange
        $prompter = $this->createStub(Prompter::class);
        $prompter->method('ask')->willReturn('bad@example.com');
        $prompter->method('secret')->willReturn('wrong');

        $session = $this->createMock(CliSession::class);
        $session->expects($this->never())->method('store');

        $output = $this->createMock(Output::class);
        $output->expects($this->once())
            ->method('errorLine')
            ->with($this->stringContains('Login failed'));

        $command = new LoginCommand(
            prompter: $prompter,
            session: $session,
            credentialsResolver: fn(string $email, string $password) => null,
        );

        // Act
        $exit = $command->execute(
            Input::fromArgv(['arcanum', 'login']),
            $output,
        );

        // Assert
        $this->assertSame(ExitCode::Failure->value, $exit);
    }

    public function testPasswordFieldUsesSecretPrompt(): void
    {
        // Arrange
        $identity = new SimpleIdentity('user-1');

        $prompter = $this->createMock(Prompter::class);
        $prompter->expects($this->once())
            ->method('ask')
            ->with('Email:')
            ->willReturn('alice@example.com');
        $prompter->expects($this->once())
            ->method('secret')
            ->with('Password:')
            ->willReturn('s3cret');

        $session = $this->createMock(CliSession::class);
        $session->expects($this->once())->method('store');

        $output = $this->createStub(Output::class);

        $command = new LoginCommand(
            prompter: $prompter,
            session: $session,
            credentialsResolver: fn(string $email, string $password) => $identity,
            fields: ['email', 'password'],
        );

        // Act
        $exit = $command->execute(
            Input::fromArgv(['arcanum', 'login']),
            $output,
        );

        // Assert
        $this->assertSame(ExitCode::Success->value, $exit);
    }

    public function testFieldsArePromptedInOrder(): void
    {
        // Arrange
        $collected = [];

        $prompter = $this->createStub(Prompter::class);
        $prompter->method('ask')->willReturnCallback(
            function (string $label) use (&$collected) {
                $collected[] = $label;
                return 'value';
            },
        );

        $session = $this->createStub(CliSession::class);
        $output = $this->createStub(Output::class);

        $command = new LoginCommand(
            prompter: $prompter,
            session: $session,
            credentialsResolver: fn() => new SimpleIdentity('1'),
            fields: ['username', 'code'],
        );

        // Act
        $command->execute(
            Input::fromArgv(['arcanum', 'login']),
            $output,
        );

        // Assert
        $this->assertSame(['Username:', 'Code:'], $collected);
    }
}
