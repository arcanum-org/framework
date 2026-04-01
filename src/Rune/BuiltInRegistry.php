<?php

declare(strict_types=1);

namespace Arcanum\Rune;

use Psr\Container\ContainerInterface;

/**
 * Registry of built-in framework commands.
 *
 * Maps unprefixed command names to BuiltInCommand class names.
 * Commands are resolved from the container on execution.
 */
final class BuiltInRegistry
{
    /**
     * @var array<string, class-string<BuiltInCommand>>
     */
    private array $commands = [];

    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    /**
     * Register a built-in command.
     *
     * @param class-string<BuiltInCommand> $commandClass
     */
    public function register(string $name, string $commandClass): void
    {
        $this->commands[$name] = $commandClass;
    }

    /**
     * Check if a command name is registered.
     */
    public function has(string $name): bool
    {
        return isset($this->commands[$name]);
    }

    /**
     * Resolve and execute a built-in command.
     */
    public function execute(string $name, Input $input, Output $output): int
    {
        if (!isset($this->commands[$name])) {
            throw new \RuntimeException(sprintf('Built-in command "%s" is not registered.', $name));
        }

        /** @var BuiltInCommand $command */
        $command = $this->container->get($this->commands[$name]);

        return $command->execute($input, $output);
    }

    /**
     * Get all registered command names.
     *
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->commands);
    }
}
