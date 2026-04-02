<?php

declare(strict_types=1);

namespace Arcanum\Rune;

/**
 * Minimal interactive stdin reader for CLI commands.
 *
 * Provides ask() for visible input and secret() for hidden input
 * (passwords, tokens). Not a full TUI — just enough for login flows.
 */
class Prompter
{
    /** @var resource */
    private readonly mixed $stdin;

    /**
     * @param resource|null $stdin Stdin stream override for testing.
     */
    public function __construct(
        private readonly Output $output,
        mixed $stdin = null,
    ) {
        $this->stdin = $stdin ?? \STDIN;
    }

    /**
     * Prompt for visible input.
     */
    public function ask(string $label): string
    {
        $this->output->write($label . ' ');

        $line = fgets($this->stdin);

        return $line === false ? '' : trim($line);
    }

    /**
     * Prompt for hidden input (passwords, tokens).
     *
     * Disables terminal echo so the typed value is not visible.
     * Falls back to visible input if stty is not available.
     */
    public function secret(string $label): string
    {
        $this->output->write($label . ' ');

        $echoDisabled = $this->disableEcho();

        $line = fgets($this->stdin);

        if ($echoDisabled) {
            $this->restoreEcho();
            $this->output->writeLine('');
        }

        return $line === false ? '' : trim($line);
    }

    private function disableEcho(): bool
    {
        if (!$this->isInteractive()) {
            return false;
        }

        exec('stty -echo 2>/dev/null', result_code: $code);

        return $code === 0;
    }

    private function restoreEcho(): void
    {
        exec('stty echo 2>/dev/null');
    }

    private function isInteractive(): bool
    {
        if ($this->stdin !== \STDIN) {
            return false;
        }

        return function_exists('posix_isatty') && posix_isatty(\STDIN);
    }
}
