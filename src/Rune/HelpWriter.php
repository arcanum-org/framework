<?php

declare(strict_types=1);

namespace Arcanum\Rune;

use Arcanum\Rune\Attribute\Description;

/**
 * Generates help output for a command/query DTO.
 *
 * Reads constructor parameters via reflection and #[Description]
 * attributes. Auto-generates a usage line from parameter signatures.
 */
final class HelpWriter
{
    public function __construct(
        private readonly Output $output,
    ) {
    }

    /**
     * Write help for a CLI command/query.
     *
     * @param string $commandName The CLI command name (e.g., 'command:contact:submit').
     * @param string $dtoClass The fully-qualified DTO class name.
     * @param bool $isCommand Whether this is a command (vs query).
     */
    public function write(string $commandName, string $dtoClass, bool $isCommand): void
    {
        $type = $isCommand ? 'command' : 'query';

        $classDescription = $this->classDescription($dtoClass);
        if ($classDescription !== null) {
            $this->output->writeLine(sprintf('%s — %s', $commandName, $classDescription));
        } else {
            $this->output->writeLine(sprintf('%s (%s)', $commandName, $type));
        }

        $this->output->writeLine('');

        if (!class_exists($dtoClass)) {
            $this->output->writeLine('  No parameters (handler-only route).');
            $this->writeUsage($commandName, []);
            return;
        }

        $ref = new \ReflectionClass($dtoClass);
        $constructor = $ref->getConstructor();

        if ($constructor === null || $constructor->getNumberOfParameters() === 0) {
            $this->output->writeLine('  No parameters.');
            $this->writeUsage($commandName, []);
            return;
        }

        $params = $constructor->getParameters();
        $this->writeParameters($params);
        $this->output->writeLine('');
        $this->writeUsage($commandName, $params);
    }

    /**
     * Write the parameter list.
     *
     * @param \ReflectionParameter[] $params
     */
    private function writeParameters(array $params): void
    {
        foreach ($params as $param) {
            $type = $param->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed';

            $suffix = $param->isDefaultValueAvailable()
                ? sprintf(' (default: %s)', json_encode($param->getDefaultValue()))
                : ' (required)';

            $description = $this->paramDescription($param);
            $descPart = $description !== null ? '  ' . $description : '';

            $this->output->writeLine(sprintf(
                '  --%-20s %s%s%s',
                $param->getName(),
                $typeName,
                $suffix,
                $descPart,
            ));
        }
    }

    /**
     * Write the auto-generated usage line.
     *
     * @param \ReflectionParameter[] $params
     */
    private function writeUsage(string $commandName, array $params): void
    {
        $this->output->writeLine('Usage:');

        $parts = ['php arcanum', $commandName];

        foreach ($params as $param) {
            $type = $param->getType();
            $typeName = $type instanceof \ReflectionNamedType ? $type->getName() : 'mixed';

            if ($typeName === 'bool') {
                $flag = sprintf('--%s', $param->getName());
            } else {
                $flag = sprintf('--%s=<%s>', $param->getName(), $typeName);
            }

            if ($param->isDefaultValueAvailable()) {
                $parts[] = sprintf('[%s]', $flag);
            } else {
                $parts[] = $flag;
            }
        }

        $this->output->writeLine('  ' . implode(' ', $parts));
    }

    /**
     * Read the #[Description] attribute from a DTO class.
     */
    private function classDescription(string $dtoClass): string|null
    {
        if (!class_exists($dtoClass)) {
            return null;
        }

        $ref = new \ReflectionClass($dtoClass);
        $attrs = $ref->getAttributes(Description::class);

        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance()->text;
    }

    /**
     * Read the #[Description] attribute from a constructor parameter.
     */
    private function paramDescription(\ReflectionParameter $param): string|null
    {
        $attrs = $param->getAttributes(Description::class);

        if ($attrs === []) {
            return null;
        }

        return $attrs[0]->newInstance()->text;
    }
}
