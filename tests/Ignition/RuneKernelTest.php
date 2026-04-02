<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition;

use Arcanum\Atlas\ConventionResolver;
use Arcanum\Atlas\Route;
use Arcanum\Atlas\Router;
use Arcanum\Atlas\UnresolvableRoute;
use Arcanum\Cabinet\Application;
use Arcanum\Codex\Hydrator;
use Arcanum\Flow\Conveyor\Bus;
use Arcanum\Flow\Conveyor\EmptyDTO;
use Arcanum\Flow\Conveyor\AcceptedDTO;
use Arcanum\Flow\Conveyor\QueryResult;
use Arcanum\Glitch\ExceptionHandler;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\RuneKernel;
use Arcanum\Atlas\CliRouter;
use Arcanum\Rune\ConsoleOutput;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Shodo\Formatters\CliFormatRegistry;
use Arcanum\Shodo\Formatters\CsvFormatter;
use Arcanum\Shodo\Formatters\JsonFormatter;
use Arcanum\Shodo\Formatters\KeyValueFormatter;
use Arcanum\Shodo\Formatters\TableFormatter;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(RuneKernel::class)]
#[UsesClass(CliRouter::class)]
#[UsesClass(CsvFormatter::class)]
#[UsesClass(JsonFormatter::class)]
#[UsesClass(KeyValueFormatter::class)]
#[UsesClass(CliFormatRegistry::class)]
#[UsesClass(ConsoleOutput::class)]
#[UsesClass(ConventionResolver::class)]
#[UsesClass(ExitCode::class)]
#[UsesClass(Input::class)]
#[UsesClass(Route::class)]
#[UsesClass(Strings::class)]
#[UsesClass(TableFormatter::class)]
final class RuneKernelTest extends TestCase
{
    // ---------------------------------------------------------------
    // Constructor & directory accessors
    // ---------------------------------------------------------------

    public function testRootDirectoryIsStored(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app');

        // Assert
        $this->assertSame('/app', $kernel->rootDirectory());
    }

    public function testConfigDirectoryDefaultsToConfigSubdirectory(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app');

        // Assert
        $this->assertSame('/app' . DIRECTORY_SEPARATOR . 'config', $kernel->configDirectory());
    }

    public function testFilesDirectoryDefaultsToFilesSubdirectory(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app');

        // Assert
        $this->assertSame('/app' . DIRECTORY_SEPARATOR . 'files', $kernel->filesDirectory());
    }

    public function testCustomConfigDirectory(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app', '/custom/config');

        // Assert
        $this->assertSame('/custom/config', $kernel->configDirectory());
    }

    public function testCustomFilesDirectory(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app', filesDirectory: '/custom/files');

        // Assert
        $this->assertSame('/custom/files', $kernel->filesDirectory());
    }

    public function testRootDirectoryTrimsTrailingSlash(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app/');

        // Assert
        $this->assertSame('/app' . DIRECTORY_SEPARATOR . 'config', $kernel->configDirectory());
        $this->assertSame('/app' . DIRECTORY_SEPARATOR . 'files', $kernel->filesDirectory());
    }

    // ---------------------------------------------------------------
    // bootstrap()
    // ---------------------------------------------------------------

    public function testBootstrapRunsBootstrappers(): void
    {
        // Arrange — RuneKernel has 5 bootstrappers (Environment, Configuration, CliRouting, Logger, Exceptions)
        $kernel = new RuneKernel('/app');

        $bootstrapper = $this->createMock(Bootstrapper::class);
        $bootstrapper->expects($this->exactly(9))->method('bootstrap');

        $container = $this->createStub(Application::class);
        $container->method('get')->willReturn($bootstrapper);

        // Act
        $kernel->bootstrap($container);
    }

    public function testBootstrapOnlyRunsOnce(): void
    {
        // Arrange
        $kernel = new RuneKernel('/app');

        $bootstrapper = $this->createMock(Bootstrapper::class);
        $bootstrapper->expects($this->exactly(9))->method('bootstrap');

        $container = $this->createStub(Application::class);
        $container->method('get')->willReturn($bootstrapper);

        // Act
        $kernel->bootstrap($container);
        $kernel->bootstrap($container);
    }

    // ---------------------------------------------------------------
    // requiredEnvironmentVariables()
    // ---------------------------------------------------------------

    public function testRequiredEnvironmentVariablesDefaultsToEmpty(): void
    {
        // Arrange & Act
        $kernel = new RuneKernel('/app');

        // Assert
        $this->assertSame([], $kernel->requiredEnvironmentVariables());
    }

    // ---------------------------------------------------------------
    // handle() — empty command
    // ---------------------------------------------------------------

    public function testHandleWithNoCommandShowsSplash(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);

        $kernel = $this->bootstrapKernel($this->containerWith(output: $output));

        // Act
        $exitCode = $kernel->handle(['bin/arcanum']);

        // Assert
        $this->assertSame(ExitCode::Success->value, $exitCode);
        $splash = $this->readStream($stdout);
        $this->assertStringContainsString('Usage:', $splash);
        $this->assertStringContainsString('php arcanum list', $splash);
    }

    // ---------------------------------------------------------------
    // handle() — successful dispatch
    // ---------------------------------------------------------------

    public function testHandleDispatchesQueryAndRendersResult(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);

        $result = new QueryResult(['status' => 'ok']);

        $bus = $this->createMock(Bus::class);
        $bus->expects($this->once())->method('dispatch')->willReturn($result);

        $kernel = $this->bootstrapKernel($this->containerWith(
            output: $output,
            bus: $bus,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
        ));

        // Act
        $exitCode = $kernel->handle(['bin/arcanum', 'query:shop:products']);

        // Assert
        $this->assertSame(ExitCode::Success->value, $exitCode);
        $this->assertStringContainsString('"status": "ok"', $this->readStream($stdout));
    }

    public function testHandleVoidCommandReturnsSilentSuccess(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);

        $bus = $this->createMock(Bus::class);
        $bus->expects($this->once())->method('dispatch')->willReturn(new EmptyDTO());

        $kernel = $this->bootstrapKernel($this->containerWith(
            output: $output,
            bus: $bus,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
        ));

        // Act
        $exitCode = $kernel->handle(['bin/arcanum', 'command:contact:submit']);

        // Assert
        $this->assertSame(ExitCode::Success->value, $exitCode);
        $this->assertSame('', $this->readStream($stdout));
    }

    public function testHandleAcceptedCommandRendersMessage(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);

        $bus = $this->createMock(Bus::class);
        $bus->expects($this->once())->method('dispatch')->willReturn(new AcceptedDTO());

        $kernel = $this->bootstrapKernel($this->containerWith(
            output: $output,
            bus: $bus,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
        ));

        // Act
        $exitCode = $kernel->handle(['bin/arcanum', 'command:contact:submit']);

        // Assert
        $this->assertSame(ExitCode::Success->value, $exitCode);
        $this->assertStringContainsString('Accepted.', $this->readStream($stdout));
    }

    // ---------------------------------------------------------------
    // handle() — help flag
    // ---------------------------------------------------------------

    public function testHandleHelpFlagRendersParameterInfo(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);

        $kernel = $this->bootstrapKernel($this->containerWith(
            output: $output,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
        ));

        // Act
        $exitCode = $kernel->handle(['bin/arcanum', 'query:shop:products', '--help']);

        // Assert
        $this->assertSame(ExitCode::Success->value, $exitCode);
        $rendered = $this->readStream($stdout);
        $this->assertStringContainsString('query:shop:products', $rendered);
        $this->assertStringContainsString('query', $rendered);
    }

    // ---------------------------------------------------------------
    // handle() — exception handling
    // ---------------------------------------------------------------

    public function testHandleUnresolvableRouteReturnsInvalidExitCode(): void
    {
        // Arrange
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);

        $kernel = $this->bootstrapKernel($this->containerWith(
            output: $output,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
        ));

        // Act
        $exitCode = $kernel->handle(['bin/arcanum', 'query:nonexistent:thing']);

        // Assert
        $this->assertSame(ExitCode::Invalid->value, $exitCode);
        $this->assertStringContainsString('No query found', $this->readStream($stderr));
    }

    public function testHandleGenericExceptionReturnsFailureExitCode(): void
    {
        // Arrange
        $stderr = $this->createStream();
        $output = new ConsoleOutput($this->createStream(), $stderr, ansi: false);

        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException('Something broke'));

        $kernel = $this->bootstrapKernel($this->containerWith(
            output: $output,
            bus: $bus,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
        ));

        // Act
        $exitCode = $kernel->handle(['bin/arcanum', 'query:shop:products']);

        // Assert
        $this->assertSame(ExitCode::Failure->value, $exitCode);
        $this->assertStringContainsString('Something broke', $this->readStream($stderr));
    }

    public function testHandleRendersViaCliFormatRegistryWhenAvailable(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);

        $result = new QueryResult(['status' => 'ok', 'version' => '1.0']);

        $bus = $this->createMock(Bus::class);
        $bus->expects($this->once())->method('dispatch')->willReturn($result);

        $kernel = $this->bootstrapKernel($this->containerWith(
            output: $output,
            bus: $bus,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
            withFormatRegistry: true,
        ));

        // Act
        $exitCode = $kernel->handle(['bin/arcanum', 'query:shop:products']);

        // Assert — KeyValueFormatter outputs key-value pairs, not JSON
        $this->assertSame(ExitCode::Success->value, $exitCode);
        $rendered = $this->readStream($stdout);
        $this->assertStringContainsString('status', $rendered);
        $this->assertStringContainsString('ok', $rendered);
        $this->assertStringNotContainsString('{', $rendered);
    }

    public function testHandleRendersJsonFormatWhenRequested(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);

        $result = new QueryResult(['status' => 'ok', 'version' => '1.0']);

        $bus = $this->createMock(Bus::class);
        $bus->expects($this->once())->method('dispatch')->willReturn($result);

        $kernel = $this->bootstrapKernel($this->containerWith(
            output: $output,
            bus: $bus,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
            withFormatRegistry: true,
        ));

        // Act
        $exitCode = $kernel->handle(['bin/arcanum', 'query:shop:products', '--format=json']);

        // Assert — JSON output with braces
        $this->assertSame(ExitCode::Success->value, $exitCode);
        $rendered = $this->readStream($stdout);
        $this->assertStringContainsString('"status"', $rendered);
        $this->assertStringContainsString('"ok"', $rendered);
        $this->assertStringContainsString('{', $rendered);
    }

    public function testHandleRendersCsvFormatWhenRequested(): void
    {
        // Arrange
        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);

        $result = new QueryResult(['status' => 'ok', 'version' => '1.0']);

        $bus = $this->createMock(Bus::class);
        $bus->expects($this->once())->method('dispatch')->willReturn($result);

        $kernel = $this->bootstrapKernel($this->containerWith(
            output: $output,
            bus: $bus,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
            withFormatRegistry: true,
        ));

        // Act
        $exitCode = $kernel->handle(['bin/arcanum', 'query:shop:products', '--format=csv']);

        // Assert — CSV output with key,value header
        $this->assertSame(ExitCode::Success->value, $exitCode);
        $rendered = $this->readStream($stdout);
        $this->assertStringContainsString('key,value', $rendered);
        $this->assertStringContainsString('status,ok', $rendered);
    }

    public function testHandleReportsExceptionToExceptionHandler(): void
    {
        // Arrange
        $output = new ConsoleOutput($this->createStream(), $this->createStream(), ansi: false);

        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException('boom'));

        $exceptionHandler = $this->createMock(ExceptionHandler::class);
        $exceptionHandler->expects($this->once())->method('handleException');

        $kernel = $this->bootstrapKernel($this->containerWith(
            output: $output,
            bus: $bus,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
            exceptionHandler: $exceptionHandler,
        ));

        // Act
        $kernel->handle(['bin/arcanum', 'query:shop:products']);
    }

    // ---------------------------------------------------------------
    // terminate()
    // ---------------------------------------------------------------

    public function testTerminateDoesNotThrow(): void
    {
        // Arrange
        $kernel = new RuneKernel('/app');

        // Act
        $kernel->terminate();

        // Assert
        $this->expectNotToPerformAssertions();
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * @return resource
     */
    private function createStream(): mixed
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        return $stream;
    }

    /**
     * @param resource $stream
     */
    private function readStream(mixed $stream): string
    {
        rewind($stream);
        $contents = stream_get_contents($stream);
        $this->assertIsString($contents);
        return $contents;
    }

    private function containerWith(
        Output|null $output = null,
        Bus|null $bus = null,
        string $routeFixtureNamespace = 'Arcanum\\Test\\Fixture',
        ExceptionHandler|null $exceptionHandler = null,
        bool $withFormatRegistry = false,
    ): Application {
        $router = new CliRouter(new ConventionResolver(rootNamespace: $routeFixtureNamespace));
        $hydrator = new Hydrator();
        $bus ??= $this->createStub(Bus::class);
        $output ??= new ConsoleOutput($this->createStream(), $this->createStream(), ansi: false);

        $formatRegistry = null;
        if ($withFormatRegistry) {
            $kvFormatter = new KeyValueFormatter();
            $jsonFormatter = new JsonFormatter();
            $csvFormatter = new CsvFormatter();
            $formatRegistryContainer = $this->createStub(\Psr\Container\ContainerInterface::class);
            $formatRegistryContainer->method('get')->willReturnCallback(
                fn(string $id): object => match ($id) {
                    KeyValueFormatter::class => $kvFormatter,
                    JsonFormatter::class => $jsonFormatter,
                    CsvFormatter::class => $csvFormatter,
                    default => throw new \RuntimeException("Unexpected formatter: $id"),
                },
            );
            $formatRegistry = new CliFormatRegistry($formatRegistryContainer);
            $formatRegistry->register('cli', KeyValueFormatter::class);
            $formatRegistry->register('json', JsonFormatter::class);
            $formatRegistry->register('csv', CsvFormatter::class);
        }

        $container = $this->createStub(Application::class);

        $container->method('has')->willReturnCallback(
            fn(string $id): bool => match ($id) {
                ExceptionHandler::class => $exceptionHandler !== null,
                CliFormatRegistry::class => $formatRegistry !== null,
                default => false,
            },
        );

        $container->method('get')->willReturnCallback(
            fn(string $id) => match ($id) {
                Router::class => $router,
                Hydrator::class => $hydrator,
                Bus::class => $bus,
                Output::class => $output,
                ExceptionHandler::class => $exceptionHandler ?? throw new \RuntimeException('No handler'),
                CliFormatRegistry::class => $formatRegistry ?? throw new \RuntimeException('No registry'),
                default => throw new \RuntimeException("Unexpected service: $id"),
            },
        );

        return $container;
    }

    private function bootstrapKernel(Application $container): RuneKernel
    {
        // Use an empty bootstrapper list so tests don't need real config files.
        $kernel = new class ('/app') extends RuneKernel {
            /** @var class-string<Bootstrapper>[] */
            protected array $bootstrappers = [];
        };

        $kernel->bootstrap($container);
        return $kernel;
    }
}
