<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Auth\CliSession;
use Arcanum\Auth\Identity;
use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Rune\Prompter;

/**
 * Interactive login for CLI sessions.
 *
 * Prompts for credentials (configurable fields), validates via the app's
 * credentials resolver, and stores the identity in an encrypted session
 * file for subsequent commands.
 *
 * Usage: php arcanum login
 */
#[Description('Log in to the CLI session')]
final class LoginCommand implements BuiltInCommand
{
    /**
     * @param list<string> $fields Field names to prompt for.
     * @param \Closure $credentialsResolver Receives field values positionally, returns Identity|null.
     */
    public function __construct(
        private readonly Prompter $prompter,
        private readonly CliSession $session,
        private readonly \Closure $credentialsResolver,
        private readonly array $fields = ['email', 'password'],
        private readonly int $ttl = 86400,
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        $values = [];

        foreach ($this->fields as $field) {
            $label = ucfirst($field) . ':';

            $values[] = $this->isSecretField($field)
                ? $this->prompter->secret($label)
                : $this->prompter->ask($label);
        }

        /** @var Identity|null $identity */
        $identity = ($this->credentialsResolver)(...$values);

        if ($identity === null) {
            $output->errorLine('Login failed. Invalid credentials.');
            return ExitCode::Failure->value;
        }

        $this->session->store($identity->id(), $this->ttl);
        $output->writeLine(sprintf('Logged in as %s.', $identity->id()));

        return ExitCode::Success->value;
    }

    private function isSecretField(string $field): bool
    {
        return in_array($field, ['password', 'secret', 'token'], true);
    }
}
