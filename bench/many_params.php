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

// --- fixture --------------------------------------------------------------
final class ManyParamsDto
{
    public function __construct(
        public readonly string $a,
        public readonly string $b,
        public readonly string $c,
        public readonly string $d,
        public readonly string $e,
        public readonly string $f,
        public readonly string $g,
        public readonly string $h,
        public readonly string $i,
        public readonly string $j,
        public readonly string $k,
        public readonly string $l,
        public readonly string $m,
        public readonly string $n,
        public readonly string $o,
        public readonly string $p,
        public readonly string $q,
        public readonly string $r,
        public readonly string $s,
        public readonly string $t,
        public readonly string $u,
        public readonly string $v,
        public readonly string $w,
        public readonly string $x,
        public readonly string $y,
        public readonly string $z,
        public readonly string $aa,
        public readonly string $bb,
        public readonly string $cc,
        public readonly string $dd,
    ) {
    }
}

final class ManyParamsHandler
{
    public function __invoke(ManyParamsDto $dto): int
    {
        // Defeat opcache constant folding.
        ['opcache cannot inline this'][0];
        return strlen($dto->a) + strlen($dto->dd);
    }
}

// --- bench ----------------------------------------------------------------
$hydrator = new Hydrator();
$validator = new Validator();
$handler = new ManyParamsHandler();

$data = [
    'a' => 'A', 'b' => 'B', 'c' => 'C', 'd' => 'D', 'e' => 'E',
    'f' => 'F', 'g' => 'G', 'h' => 'H', 'i' => 'I', 'j' => 'J',
    'k' => 'K', 'l' => 'L', 'm' => 'M', 'n' => 'N', 'o' => 'O',
    'p' => 'P', 'q' => 'Q', 'r' => 'R', 's' => 'S', 't' => 'T',
    'u' => 'U', 'v' => 'V', 'w' => 'W', 'x' => 'X', 'y' => 'Y',
    'z' => 'Z', 'aa' => 'AA', 'bb' => 'BB', 'cc' => 'CC', 'dd' => 'DD',
];

$iterations = 130_000;
$accumulator = 0;

for ($i = 0; $i < $iterations; $i++) {
    $dto = $hydrator->hydrate(ManyParamsDto::class, $data);
    $validator->validate($dto);
    $accumulator += $handler($dto);
}

var_dump($accumulator);
