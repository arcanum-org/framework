<?php

declare(strict_types=1);

namespace Arcanum\Auth;

use Arcanum\Rune\Input;

/**
 * Resolves an Identity from CLI arguments, session, or environment.
 *
 * Priority chain:
 *   1. --token flag → call provider's findByToken()
 *   2. CliSession → if stored and not expired, call provider's findById()
 *   3. ARCANUM_TOKEN env var → call provider's findByToken()
 *
 * The --token option always takes precedence. A stored CLI session
 * (from `login` command) is checked next. The environment variable
 * is the fallback for CI/scripting environments.
 */
final class CliAuthResolver
{
    public function __construct(
        private readonly ActiveIdentity $activeIdentity,
        private readonly IdentityProvider $provider,
        private readonly CliSession|null $session = null,
    ) {
    }

    public function resolve(Input $input): void
    {
        // 1. --token flag (highest priority)
        $token = $input->option('token');

        if ($token !== null && $token !== '') {
            $this->resolveFromToken($token);
            return;
        }

        // 2. Stored CLI session
        if ($this->session !== null) {
            $identityId = $this->session->load();

            if ($identityId !== null) {
                $identity = $this->provider->findById($identityId);

                if ($identity !== null) {
                    $this->activeIdentity->set($identity);
                    return;
                }
            }
        }

        // 3. ARCANUM_TOKEN env var
        $env = getenv('ARCANUM_TOKEN');
        $envToken = is_string($env) && $env !== '' ? $env : null;

        if ($envToken !== null) {
            $this->resolveFromToken($envToken);
        }
    }

    private function resolveFromToken(string $token): void
    {
        $identity = $this->provider->findByToken($token);

        if ($identity !== null) {
            $this->activeIdentity->set($identity);
        }
    }
}
