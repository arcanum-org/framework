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

use Arcanum\Auth\ActiveIdentity;
use Arcanum\Auth\AuthorizationGuard;
use Arcanum\Codex\Hydrator;
use Arcanum\Flow\Conveyor\Middleware\TransportGuard;
use Arcanum\Flow\Conveyor\MiddlewareBus;
use Arcanum\Ignition\Transport;
use Arcanum\Validation\Rule\Email;
use Arcanum\Validation\Rule\MaxLength;
use Arcanum\Validation\Rule\MinLength;
use Arcanum\Validation\Rule\NotEmpty;
use Arcanum\Validation\Rule\Pattern;
use Arcanum\Validation\Rule\Url;
use Arcanum\Validation\Rule\Uuid;
use Arcanum\Validation\ValidationGuard;
use Psr\Container\ContainerInterface;

// --- fixture --------------------------------------------------------------
final class FullPipelineCommand
{
    public function __construct(
        #[NotEmpty] #[Email] #[MaxLength(255)]
        public readonly string $email,
        #[NotEmpty] #[Url] #[MaxLength(2048)]
        public readonly string $websiteUrl,
        #[NotEmpty] #[Uuid]
        public readonly string $userId,
        #[NotEmpty] #[MinLength(2)] #[MaxLength(64)] #[Pattern('/^[A-Za-z0-9 ]+$/')]
        public readonly string $name,
        #[MaxLength(500)] #[Pattern('/^[^<>]*$/')]
        public readonly string $bio = '',
    ) {
    }
}

final class FullPipelineCommandHandler
{
    public function __invoke(FullPipelineCommand $cmd): int
    {
        // Defeat opcache constant folding before producing the result.
        ['opcache cannot inline this'][0];
        return strlen($cmd->email) + strlen($cmd->websiteUrl) + strlen($cmd->userId) + strlen($cmd->name) + strlen($cmd->bio);
    }
}

// --- minimal PSR-11 container --------------------------------------------
$handler = new FullPipelineCommandHandler();
$container = new class ($handler) implements ContainerInterface {
    /** @param object $handler */
    public function __construct(private readonly object $handler)
    {
    }

    public function get(string $id): object
    {
        if ($id === FullPipelineCommandHandler::class) {
            return $this->handler;
        }
        throw new RuntimeException("No entry: $id");
    }

    public function has(string $id): bool
    {
        return $id === FullPipelineCommandHandler::class;
    }
};

// --- pipeline wiring ------------------------------------------------------
$hydrator = new Hydrator();

$bus = new MiddlewareBus($container);
$bus->before(
    new ValidationGuard(),
    new AuthorizationGuard(new ActiveIdentity(), Transport::Http, $container),
    new TransportGuard(Transport::Http),
);

// --- bench ----------------------------------------------------------------
$data = [
    'email'      => 'alice@example.com',
    'websiteUrl' => 'https://example.com/profile',
    'userId'     => '550e8400-e29b-41d4-a716-446655440000',
    'name'       => 'Alice Example',
    'bio'        => 'Backend engineer who enjoys CQRS and small mental models.',
];

$iterations = 90_000;
$accumulator = 0;

for ($i = 0; $i < $iterations; $i++) {
    $cmd = $hydrator->hydrate(FullPipelineCommand::class, $data);
    $result = $bus->dispatch($cmd);
    // $result is a QueryResult wrapping the int — pull the value out so opcache
    // can't elide the dispatch chain.
    $accumulator += (int) $result->data;
}

var_dump($accumulator);
