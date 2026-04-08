<?php

declare(strict_types=1);

// --- environment guards ---------------------------------------------------
if (!extension_loaded('Zend OPcache') || !ini_get('opcache.enable_cli')) {
    throw new RuntimeException('Bench requires opcache + opcache.enable_cli=1');
}
if (extension_loaded('xdebug')) {
    throw new RuntimeException('Bench must run without xdebug loaded');
}
$status = opcache_get_status(false);
if (!is_array($status) || ($status['jit']['enabled'] ?? false) !== true) {
    throw new RuntimeException('Bench requires JIT enabled — some extension is hooking zend_execute_ex (pcov? blackfire?)');
}

require __DIR__ . '/../vendor/autoload.php';

use Arcanum\Codex\Hydrator;
use Arcanum\Validation\Validator;
use Arcanum\Validation\Rule\MaxLength;
use Arcanum\Validation\Rule\MinLength;
use Arcanum\Validation\Rule\NotEmpty;
use Arcanum\Validation\Rule\Pattern;

// --- fixture --------------------------------------------------------------
final class HeavyValidationDto
{
    public function __construct(
        #[NotEmpty] #[MinLength(1)] #[MaxLength(255)] #[Pattern('/^[A-Za-z0-9]+$/')]
        public readonly string $a,
        #[NotEmpty] #[MinLength(1)] #[MaxLength(255)] #[Pattern('/^[A-Za-z0-9]+$/')]
        public readonly string $b,
        #[NotEmpty] #[MinLength(1)] #[MaxLength(255)] #[Pattern('/^[A-Za-z0-9]+$/')]
        public readonly string $c,
        #[NotEmpty] #[MinLength(1)] #[MaxLength(255)] #[Pattern('/^[A-Za-z0-9]+$/')]
        public readonly string $d,
        #[NotEmpty] #[MinLength(1)] #[MaxLength(255)] #[Pattern('/^[A-Za-z0-9]+$/')]
        public readonly string $e,
    ) {
    }
}

final class HeavyValidationHandler
{
    public function __invoke(HeavyValidationDto $dto): int
    {
        // Defeat opcache constant folding by referencing a non-foldable expression
        // before producing the result.
        ['opcache cannot inline this'][0];
        return strlen($dto->a) + strlen($dto->b) + strlen($dto->c) + strlen($dto->d) + strlen($dto->e);
    }
}

// --- bench ----------------------------------------------------------------
$hydrator = new Hydrator();
$validator = new Validator();
$handler = new HeavyValidationHandler();

$data = ['a' => 'Alpha', 'b' => 'Bravo', 'c' => 'Charlie', 'd' => 'Delta', 'e' => 'Echo'];

$iterations = 130_000;
$accumulator = 0;

for ($i = 0; $i < $iterations; $i++) {
    $dto = $hydrator->hydrate(HeavyValidationDto::class, $data);
    $validator->validate($dto);
    $accumulator += $handler($dto);
}

// Force the accumulator to be observed so the loop body cannot be elided.
var_dump($accumulator);
