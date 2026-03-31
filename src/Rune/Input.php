<?php

declare(strict_types=1);

namespace Arcanum\Rune;

/**
 * Parses CLI arguments into a structured representation.
 *
 * Given a raw $argv array, Input extracts the command name, positional
 * arguments, named options (--key=value or --key value), and boolean
 * flags (--verbose). The script name ($argv[0]) is discarded.
 */
final class Input
{
    /** @var list<string> */
    private array $arguments;

    /** @var array<string, string> */
    private array $options;

    /** @var array<string, bool> */
    private array $flags;

    /**
     * @param list<string> $arguments
     * @param array<string, string> $options
     * @param array<string, bool> $flags
     */
    public function __construct(
        private readonly string $command,
        array $arguments = [],
        array $options = [],
        array $flags = [],
    ) {
        $this->arguments = $arguments;
        $this->options = $options;
        $this->flags = $flags;
    }

    /**
     * Parse a raw $argv array into an Input instance.
     *
     * @param list<string> $argv
     */
    public static function fromArgv(array $argv): self
    {
        // Discard the script name.
        array_shift($argv);

        $command = array_shift($argv) ?? '';
        $arguments = [];
        $options = [];
        $flags = [];

        for ($i = 0; $i < count($argv); $i++) {
            $token = $argv[$i];

            // End of options marker — everything after is positional.
            if ($token === '--') {
                $arguments = array_merge($arguments, array_slice($argv, $i + 1));
                break;
            }

            if (str_starts_with($token, '--')) {
                $name = substr($token, 2);

                // --key=value form
                if (str_contains($name, '=')) {
                    [$key, $value] = explode('=', $name, 2);
                    $options[$key] = $value;
                    continue;
                }

                // Check if next token is a value (not another flag).
                $nextIndex = $i + 1;
                if ($nextIndex < count($argv) && !str_starts_with($argv[$nextIndex], '--')) {
                    $options[$name] = $argv[$nextIndex];
                    $i++;
                    continue;
                }

                // No value — boolean flag.
                $flags[$name] = true;
                continue;
            }

            $arguments[] = $token;
        }

        return new self($command, $arguments, $options, $flags);
    }

    /**
     * The command name (first argument after the script name).
     */
    public function command(): string
    {
        return $this->command;
    }

    /**
     * Positional arguments (non-flag values after the command).
     *
     * @return list<string>
     */
    public function arguments(): array
    {
        return $this->arguments;
    }

    /**
     * Get a positional argument by index, or null if not present.
     */
    public function argument(int $index): string|null
    {
        return $this->arguments[$index] ?? null;
    }

    /**
     * Named options (--key=value or --key value pairs).
     *
     * @return array<string, string>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * Get a named option by key, or the default if not present.
     */
    public function option(string $key, string|null $default = null): string|null
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * Whether a named option is present.
     */
    public function hasOption(string $key): bool
    {
        return array_key_exists($key, $this->options);
    }

    /**
     * Boolean flags (--verbose, --no-ansi, etc.).
     *
     * @return array<string, bool>
     */
    public function flags(): array
    {
        return $this->flags;
    }

    /**
     * Whether a boolean flag is set.
     */
    public function hasFlag(string $name): bool
    {
        return $this->flags[$name] ?? false;
    }
}
