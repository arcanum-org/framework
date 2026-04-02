<?php

declare(strict_types=1);

namespace Arcanum\Ignition;

use Arcanum\Atlas\Router;
use Arcanum\Atlas\UnresolvableRoute;
use Arcanum\Cabinet\Application;
use Arcanum\Codex\Hydrator;
use Arcanum\Gather\Configuration;
use Arcanum\Flow\Conveyor\Bus;
use Arcanum\Flow\Conveyor\Command;
use Arcanum\Flow\Conveyor\EmptyDTO;
use Arcanum\Flow\Conveyor\AcceptedDTO;
use Arcanum\Flow\Conveyor\Query;
use Arcanum\Flow\Conveyor\QueryResult;
use Arcanum\Glitch\ExceptionHandler;
use Arcanum\Rune\CliExceptionWriter;
use Arcanum\Rune\BuiltInRegistry;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\HelpWriter;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Shodo\CliFormatRegistry;

/**
 * The CLI entry point — parallel to HyperKernel for HTTP.
 *
 * RuneKernel shares the same Kernel interface and transport-agnostic
 * bootstrappers (Environment, Configuration, Logger, Exceptions) but
 * skips HTTP-specific bootstrappers (Middleware, RouteMiddleware).
 *
 * Subclass this in your application to customize bootstrappers or
 * register built-in framework commands.
 */
class RuneKernel implements Kernel
{
    private bool $isBootstrapped = false;

    protected Application $container;

    /**
     * Environment variables that must be set for the application to run.
     * Override this in your app's CLI Kernel to enforce required env vars.
     *
     * @var string[]
     */
    protected array $requiredEnvironmentVariables = [];

    /**
     * The bootstrappers to run before handling a command.
     *
     * Reuses transport-agnostic bootstrappers from Ignition. Skips
     * Middleware and RouteMiddleware (PSR-15 specific).
     *
     * @var class-string<Bootstrapper>[]
     */
    protected array $bootstrappers = [
        Bootstrap\Environment::class,
        Bootstrap\Configuration::class,
        Bootstrap\Security::class,
        Bootstrap\Cache::class,
        Bootstrap\Database::class,
        Bootstrap\Auth::class,
        Bootstrap\CliRouting::class,
        Bootstrap\Logger::class,
        Bootstrap\Exceptions::class,
    ];

    public function __construct(
        private readonly string $rootDirectory,
        private string $configDirectory = '',
        private string $filesDirectory = '',
    ) {
        $root = rtrim($rootDirectory, DIRECTORY_SEPARATOR);

        if ($configDirectory === '') {
            $this->configDirectory = $root . DIRECTORY_SEPARATOR . 'config';
        }
        if ($filesDirectory === '') {
            $this->filesDirectory = $root . DIRECTORY_SEPARATOR . 'files';
        }
    }

    /**
     * @return string[]
     */
    public function requiredEnvironmentVariables(): array
    {
        return $this->requiredEnvironmentVariables;
    }

    public function rootDirectory(): string
    {
        return $this->rootDirectory;
    }

    public function configDirectory(): string
    {
        return $this->configDirectory;
    }

    public function filesDirectory(): string
    {
        return $this->filesDirectory;
    }

    public function bootstrap(Application $container): void
    {
        if ($this->isBootstrapped) {
            return;
        }

        $this->container = $container;
        $container->instance(Transport::class, Transport::Cli);

        foreach ($this->bootstrappers as $name) {
            /** @var Bootstrapper $bootstrapper */
            $bootstrapper = $container->get($name);
            $bootstrapper->bootstrap($container);
        }

        $this->isBootstrapped = true;
    }

    /**
     * Handle CLI input and return an exit code.
     *
     * Parses argv, routes to a DTO, hydrates, dispatches through
     * Conveyor, and renders output. Exceptions are caught and
     * written to stderr.
     *
     * @param list<string> $argv Raw CLI arguments from $argv.
     */
    public function handle(array $argv): int
    {
        $input = Input::fromArgv($argv);

        /** @var Output $output */
        $output = $this->container->get(Output::class);

        if ($input->command() === '') {
            $this->splash($output);
            return ExitCode::Success->value;
        }

        try {
            return $this->handleInput($input, $output);
        } catch (\Throwable $e) {
            return $this->handleException($e, $output);
        }
    }

    /**
     * Route, hydrate, dispatch, and render.
     */
    protected function handleInput(Input $input, Output $output): int
    {
        // Built-in commands (unprefixed) take priority over domain routing.
        if ($this->container->has(BuiltInRegistry::class)) {
            /** @var BuiltInRegistry $builtIns */
            $builtIns = $this->container->get(BuiltInRegistry::class);
            if ($builtIns->has($input->command())) {
                return $builtIns->execute($input->command(), $input, $output);
            }
        }

        // Resolve CLI authentication before domain dispatch.
        if ($this->container->has(\Arcanum\Auth\CliAuthResolver::class)) {
            /** @var \Arcanum\Auth\CliAuthResolver $authResolver */
            $authResolver = $this->container->get(\Arcanum\Auth\CliAuthResolver::class);
            $authResolver->resolve($input);
        }

        /** @var Router $router */
        $router = $this->container->get(Router::class);

        $route = $router->resolve($input);

        if ($route->isHelp) {
            $helpWriter = new HelpWriter($output);
            $helpWriter->write($input->command(), $route->dtoClass, $route->isCommand());
            return ExitCode::Success->value;
        }

        /** @var Hydrator $hydrator */
        $hydrator = $this->container->get(Hydrator::class);

        /** @var Bus $bus */
        $bus = $this->container->get(Bus::class);

        $data = $input->all();

        if (class_exists($route->dtoClass)) {
            /** @var class-string<object> $dtoClass */
            $dtoClass = $route->dtoClass;
            $dto = $hydrator->hydrate($dtoClass, $data);
        } elseif ($route->isCommand()) {
            $dto = new Command($route->dtoClass, $data);
        } else {
            $dto = new Query($route->dtoClass, $data);
        }

        $result = $bus->dispatch($dto, $route->handlerPrefix);

        return $this->renderResult($result, $route, $output);
    }

    /**
     * Render a dispatch result to output.
     */
    protected function renderResult(object $result, \Arcanum\Atlas\Route $route, Output $output): int
    {
        if ($result instanceof EmptyDTO) {
            return ExitCode::Success->value;
        }

        if ($result instanceof AcceptedDTO) {
            $output->writeLine('Accepted.');
            return ExitCode::Success->value;
        }

        if ($result instanceof QueryResult) {
            $result = $result->data;
        }

        if ($this->container->has(CliFormatRegistry::class)) {
            /** @var CliFormatRegistry $formats */
            $formats = $this->container->get(CliFormatRegistry::class);
            $rendered = $formats->formatter($route->format)->format($result);
            if ($rendered !== '') {
                $output->writeLine($rendered);
            }
        } else {
            if (is_object($result)) {
                $result = get_object_vars($result);
            }
            $output->writeLine((string) json_encode($result, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
        }

        return ExitCode::Success->value;
    }

    /**
     * Handle an exception during dispatch.
     */
    protected function handleException(\Throwable $e, Output $output): int
    {
        if ($this->container->has(ExceptionHandler::class)) {
            /** @var ExceptionHandler $handler */
            $handler = $this->container->get(ExceptionHandler::class);
            $handler->handleException($e);
        }

        if ($this->container->has(CliExceptionWriter::class)) {
            /** @var CliExceptionWriter $renderer */
            $renderer = $this->container->get(CliExceptionWriter::class);
            $renderer->render($e);
        } else {
            $output->errorLine('Error: ' . $e->getMessage());
        }

        if ($e instanceof UnresolvableRoute) {
            return ExitCode::Invalid->value;
        }

        return ExitCode::Failure->value;
    }

    public function terminate(): void
    {
    }

    private function splash(Output $output): void
    {
        $name = 'Arcanum';

        if ($this->container->has(Configuration::class)) {
            /** @var Configuration $config */
            $config = $this->container->get(Configuration::class);
            $configured = $config->get('app.name');
            if (is_string($configured) && $configured !== '') {
                $name = $configured;
            }
        }

        $output->writeLine($name);
        $output->writeLine('');
        $output->writeLine('Usage:');
        $output->writeLine('  php arcanum <command>');
        $output->writeLine('');
        $output->writeLine('Run a query or command:');
        $output->writeLine('  php arcanum query:<domain>:<name>');
        $output->writeLine('  php arcanum command:<domain>:<name>');
        $output->writeLine('');
        $output->writeLine('Examples:');
        $output->writeLine('  php arcanum query:health');
        $output->writeLine('  php arcanum command:contact:submit --name="Alice"');
        $output->writeLine('');
        $output->writeLine('Common commands:');
        $output->writeLine('  list                 Show all available commands');
        $output->writeLine('  help <command>       Show help for a command');
        $output->writeLine('  login                Log in to the CLI session');
        $output->writeLine('  db:status            Show database connections');
        $output->writeLine('');
        $output->writeLine('Run "php arcanum list" for the full command list.');
    }
}
