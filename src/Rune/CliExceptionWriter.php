<?php

declare(strict_types=1);

namespace Arcanum\Rune;

use Arcanum\Validation\ValidationException;

/**
 * Writes exceptions as formatted error messages to CLI output.
 *
 * Debug mode shows the exception class, message, file, line, and stack trace.
 * Production mode shows only the message.
 * ValidationException gets a dedicated format showing field-level errors.
 */
final class CliExceptionWriter
{
    public function __construct(
        private readonly Output $output,
        private readonly bool $debug = false,
    ) {
    }

    public function render(\Throwable $e): void
    {
        if ($e instanceof ValidationException) {
            $this->renderValidation($e);
            return;
        }

        if ($this->debug) {
            $this->renderDebug($e);
            return;
        }

        $this->output->errorLine('Error: ' . $e->getMessage());
    }

    private function renderValidation(ValidationException $e): void
    {
        $this->output->errorLine('Validation failed:');

        foreach ($e->errorsByField() as $field => $messages) {
            foreach ($messages as $message) {
                $this->output->errorLine(sprintf('  %s: %s', $field, $message));
            }
        }
    }

    private function renderDebug(\Throwable $e): void
    {
        $this->output->errorLine(sprintf(
            '[%s] %s',
            get_class($e),
            $e->getMessage(),
        ));
        $this->output->errorLine(sprintf(
            '  in %s:%d',
            $e->getFile(),
            $e->getLine(),
        ));
        $this->output->error('');

        foreach ($e->getTrace() as $i => $frame) {
            $file = $frame['file'] ?? 'unknown';
            $line = $frame['line'] ?? 0;
            $call = $this->formatCall($frame);
            $this->output->errorLine(sprintf('  #%d %s:%d %s', $i, $file, $line, $call));
        }

        if ($e->getPrevious() !== null) {
            $this->output->errorLine('');
            $this->output->errorLine('Caused by:');
            $this->renderDebug($e->getPrevious());
        }
    }

    /**
     * Format a stack trace frame's function call.
     *
     * @param array<string, mixed> $frame
     */
    private function formatCall(array $frame): string
    {
        $function = isset($frame['function']) && is_string($frame['function'])
            ? $frame['function']
            : '???';

        if (isset($frame['class']) && is_string($frame['class'])) {
            $type = isset($frame['type']) && is_string($frame['type']) ? $frame['type'] : '::';
            return $frame['class'] . $type . $function . '()';
        }

        return $function . '()';
    }
}
