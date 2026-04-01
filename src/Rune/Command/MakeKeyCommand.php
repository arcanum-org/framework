<?php

declare(strict_types=1);

namespace Arcanum\Rune\Command;

use Arcanum\Rune\Attribute\Description;
use Arcanum\Rune\BuiltInCommand;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Toolkit\Random;

/**
 * Generates a new APP_KEY for the application.
 *
 * Prints `APP_KEY=base64:<key>` to stdout. With `--write`, updates the `.env` file directly.
 */
#[Description('Generate a new APP_KEY for encryption and signing')]
final class MakeKeyCommand implements BuiltInCommand
{
    public function __construct(
        private readonly string $rootDirectory,
    ) {
    }

    public function execute(Input $input, Output $output): int
    {
        $keyBytes = Random::bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $encoded = 'base64:' . base64_encode($keyBytes);
        $line = 'APP_KEY=' . $encoded;

        if ($input->hasFlag('write')) {
            return $this->writeToEnv($line, $encoded, $output);
        }

        $output->writeLine($line);
        return ExitCode::Success->value;
    }

    private function writeToEnv(string $line, string $encoded, Output $output): int
    {
        $envPath = $this->rootDirectory . DIRECTORY_SEPARATOR . '.env';

        if (!file_exists($envPath)) {
            file_put_contents($envPath, $line . "\n");
            $output->writeLine('APP_KEY written to .env');
            return ExitCode::Success->value;
        }

        $contents = file_get_contents($envPath);

        if ($contents === false) {
            $output->errorLine('Could not read .env file.');
            return ExitCode::Failure->value;
        }

        // Replace existing APP_KEY line, or append if not present.
        if (preg_match('/^APP_KEY=.*$/m', $contents)) {
            $contents = preg_replace('/^APP_KEY=.*$/m', $line, $contents);
        } else {
            $contents = rtrim($contents, "\n") . "\n" . $line . "\n";
        }

        file_put_contents($envPath, $contents);
        $output->writeLine('APP_KEY written to .env');

        return ExitCode::Success->value;
    }
}
