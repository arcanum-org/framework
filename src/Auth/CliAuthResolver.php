<?php

declare(strict_types=1);

namespace Arcanum\Auth;

use Arcanum\Rune\Input;

/**
 * Resolves an Identity from CLI arguments or environment.
 *
 * Not middleware — called during CLI bootstrap. Reads a token from
 * the --token option or the ARCANUM_TOKEN environment variable.
 * Calls the app-provided resolver to validate the token and return
 * an Identity. Writes to ActiveIdentity if resolved.
 *
 * The --token option takes precedence over the environment variable.
 */
final class CliAuthResolver
{
    /**
     * @param \Closure(string): (Identity|null) $resolver
     */
    public function __construct(
        private readonly ActiveIdentity $activeIdentity,
        private readonly \Closure $resolver,
    ) {
    }

    public function resolve(Input $input): void
    {
        $token = $input->option('token');

        if ($token === null || $token === '') {
            $env = getenv('ARCANUM_TOKEN');
            $token = is_string($env) && $env !== '' ? $env : null;
        }

        if ($token === null) {
            return;
        }

        $identity = ($this->resolver)($token);

        if ($identity !== null) {
            $this->activeIdentity->set($identity);
        }
    }
}
