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
use Arcanum\Ignition\Bootstrap;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\Lifecycle;
use Arcanum\Ignition\RuneKernel;
use Arcanum\Atlas\CliRouter;
use Arcanum\Rune\ConsoleOutput;
use Arcanum\Rune\Event\CommandCompleted;
use Arcanum\Rune\Event\CommandFailed;
use Arcanum\Rune\Event\CommandHandled;
use Arcanum\Rune\Event\CommandReceived;
use Arcanum\Rune\ExitCode;
use Arcanum\Rune\Input;
use Arcanum\Rune\Output;
use Arcanum\Shodo\CliFormatRegistry;
use Arcanum\Shodo\Formatters\CsvFormatter;
use Arcanum\Shodo\Formatters\JsonFormatter;
use Arcanum\Shodo\Formatters\KeyValueFormatter;
use Arcanum\Shodo\Formatters\TableFormatter;
use Arcanum\Toolkit\Strings;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
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
#[UsesClass(Lifecycle::class)]
#[UsesClass(Route::class)]
#[UsesClass(Strings::class)]
#[UsesClass(CommandCompleted::class)]
#[UsesClass(CommandFailed::class)]
#[UsesClass(CommandHandled::class)]
#[UsesClass(CommandReceived::class)]
#[UsesClass(TableFormatter::class)]
#[UsesClass(\Arcanum\Toolkit\Random::class)]
#[UsesClass(\Arcanum\Toolkit\Hex::class)]
#[UsesClass(\Arcanum\Quill\CorrelationProcessor::class)]
#[UsesClass(\Arcanum\Gather\Configuration::class)]
final class RuneKernelTest extends TestCase
{
    private mixed $originalArgv = null;

    protected function setUp(): void
    {
        $this->originalArgv = $_SERVER['argv'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->originalArgv !== null) {
            $_SERVER['argv'] = $this->originalArgv;
        }
    }

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
        $bootstrapper->expects($this->exactly(10))->method('bootstrap');

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
        $bootstrapper->expects($this->exactly(10))->method('bootstrap');

        $container = $this->createStub(Application::class);
        $container->method('get')->willReturn($bootstrapper);

        // Act
        $kernel->bootstrap($container);
        $kernel->bootstrap($container);
    }

    // ---------------------------------------------------------------
    // Per-command bootstrap
    // ---------------------------------------------------------------

    public function testMakeKeyCommandGetsMinimalBootstrap(): void
    {
        // Arrange — simulate `php arcanum make:key`
        $_SERVER['argv'] = ['bin/arcanum', 'make:key'];

        $resolved = [];
        $bootstrapper = $this->createStub(Bootstrapper::class);

        $container = $this->createStub(Application::class);
        $container->method('get')->willReturnCallback(
            function (string $id) use ($bootstrapper, &$resolved) {
                $resolved[] = $id;
                return $bootstrapper;
            },
        );

        $kernel = new RuneKernel('/app');

        // Act
        $kernel->bootstrap($container);

        // Assert — only early bootstrappers ran
        $this->assertContains(Bootstrap\Hourglass::class, $resolved);
        $this->assertContains(Bootstrap\Environment::class, $resolved);
        $this->assertContains(Bootstrap\Configuration::class, $resolved);
        $this->assertNotContains(Bootstrap\Security::class, $resolved);
        $this->assertNotContains(Bootstrap\Database::class, $resolved);
        $this->assertNotContains(Bootstrap\Auth::class, $resolved);
    }

    public function testListCommandGetsMinimalBootstrap(): void
    {
        // Arrange — simulate `php arcanum list`
        $_SERVER['argv'] = ['bin/arcanum', 'list'];

        $resolved = [];
        $bootstrapper = $this->createStub(Bootstrapper::class);

        $container = $this->createStub(Application::class);
        $container->method('get')->willReturnCallback(
            function (string $id) use ($bootstrapper, &$resolved) {
                $resolved[] = $id;
                return $bootstrapper;
            },
        );

        $kernel = new RuneKernel('/app');

        // Act
        $kernel->bootstrap($container);

        // Assert — only 3 early bootstrappers, not the full 10
        $this->assertCount(3, $resolved);
    }

    public function testHelpCommandGetsMinimalBootstrap(): void
    {
        // Arrange — simulate `php arcanum help`
        $_SERVER['argv'] = ['bin/arcanum', 'help'];

        $resolved = [];
        $bootstrapper = $this->createStub(Bootstrapper::class);

        $container = $this->createStub(Application::class);
        $container->method('get')->willReturnCallback(
            function (string $id) use ($bootstrapper, &$resolved) {
                $resolved[] = $id;
                return $bootstrapper;
            },
        );

        $kernel = new RuneKernel('/app');

        // Act
        $kernel->bootstrap($container);

        // Assert
        $this->assertCount(3, $resolved);
    }

    public function testNormalCommandGetsFullBootstrap(): void
    {
        // Arrange — simulate `php arcanum migrate`
        $_SERVER['argv'] = ['bin/arcanum', 'migrate'];

        $resolved = [];
        $bootstrapper = $this->createStub(Bootstrapper::class);

        $container = $this->createStub(Application::class);
        $container->method('get')->willReturnCallback(
            function (string $id) use ($bootstrapper, &$resolved) {
                $resolved[] = $id;
                return $bootstrapper;
            },
        );

        $kernel = new RuneKernel('/app');

        // Act
        $kernel->bootstrap($container);

        // Assert — all 10 bootstrappers ran
        $this->assertCount(10, $resolved);
        $this->assertContains(Bootstrap\Security::class, $resolved);
        $this->assertContains(Bootstrap\Database::class, $resolved);
    }

    public function testEmptyCommandGetsFullBootstrap(): void
    {
        // Arrange — simulate `php arcanum` (splash screen)
        $_SERVER['argv'] = ['bin/arcanum'];

        $resolved = [];
        $bootstrapper = $this->createStub(Bootstrapper::class);

        $container = $this->createStub(Application::class);
        $container->method('get')->willReturnCallback(
            function (string $id) use ($bootstrapper, &$resolved) {
                $resolved[] = $id;
                return $bootstrapper;
            },
        );

        $kernel = new RuneKernel('/app');

        // Act
        $kernel->bootstrap($container);

        // Assert — full bootstrap for the splash screen
        $this->assertCount(10, $resolved);
    }

    public function testAppConfigOverridesFrameworkDefaults(): void
    {
        // Arrange — simulate `php arcanum make:key`, but app config adds Security
        $_SERVER['argv'] = ['bin/arcanum', 'make:key'];

        $resolved = [];
        $bootstrapper = $this->createStub(Bootstrapper::class);

        $config = new \Arcanum\Gather\Configuration([
            'bootstrap' => [
                'cli' => [
                    'make:key' => [Bootstrap\Security::class],
                ],
            ],
        ]);

        $container = $this->createStub(Application::class);
        $container->method('has')->willReturnCallback(
            fn(string $id): bool => match ($id) {
                \Arcanum\Gather\Configuration::class => true,
                default => false,
            },
        );
        $container->method('get')->willReturnCallback(
            function (string $id) use ($bootstrapper, $config, &$resolved) {
                if ($id === \Arcanum\Gather\Configuration::class) {
                    return $config;
                }
                $resolved[] = $id;
                return $bootstrapper;
            },
        );

        $kernel = new RuneKernel('/app');

        // Act
        $kernel->bootstrap($container);

        // Assert — early bootstrappers + Security from app override
        $this->assertContains(Bootstrap\Hourglass::class, $resolved);
        $this->assertContains(Bootstrap\Environment::class, $resolved);
        $this->assertContains(Bootstrap\Configuration::class, $resolved);
        $this->assertContains(Bootstrap\Security::class, $resolved);
        $this->assertCount(4, $resolved);
    }

    public function testEarlyBootstrappersNotDuplicatedInPerCommandList(): void
    {
        // Arrange — app config lists Environment in the per-command list; it should not run twice
        $_SERVER['argv'] = ['bin/arcanum', 'custom:thing'];

        $resolved = [];
        $bootstrapper = $this->createStub(Bootstrapper::class);

        $config = new \Arcanum\Gather\Configuration([
            'bootstrap' => [
                'cli' => [
                    'custom:thing' => [
                        Bootstrap\Environment::class,
                        Bootstrap\Security::class,
                    ],
                ],
            ],
        ]);

        $container = $this->createStub(Application::class);
        $container->method('has')->willReturnCallback(
            fn(string $id): bool => match ($id) {
                \Arcanum\Gather\Configuration::class => true,
                default => false,
            },
        );
        $container->method('get')->willReturnCallback(
            function (string $id) use ($bootstrapper, $config, &$resolved) {
                if ($id === \Arcanum\Gather\Configuration::class) {
                    return $config;
                }
                $resolved[] = $id;
                return $bootstrapper;
            },
        );

        $kernel = new RuneKernel('/app');

        // Act
        $kernel->bootstrap($container);

        // Assert — Environment runs once (early), Security runs once (custom list)
        $environmentCount = count(array_filter(
            $resolved,
            fn(string $id) => $id === Bootstrap\Environment::class,
        ));
        $this->assertSame(1, $environmentCount);
        $this->assertContains(Bootstrap\Security::class, $resolved);
        $this->assertCount(4, $resolved); // Hourglass, Environment, Configuration, Security
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

    public function testTerminateDoesNotThrowWithoutPriorHandle(): void
    {
        // Arrange
        $kernel = $this->bootstrapKernel($this->containerWith());

        // Act
        $kernel->terminate();

        // Assert — no CommandCompleted dispatched when no command was handled
        $this->addToAssertionCount(1);
    }

    // ---------------------------------------------------------------
    // Lifecycle events
    // ---------------------------------------------------------------

    public function testCommandReceivedFiresBeforeDispatch(): void
    {
        // Arrange
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->atLeastOnce())->method('dispatch')->willReturnCallback(
            function (object $event) {
                return $event;
            },
        );

        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')->willReturn(new EmptyDTO());

        $kernel = $this->bootstrapKernel($this->containerWith(
            bus: $bus,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
            eventDispatcher: $dispatcher,
        ));

        // Act
        $kernel->handle(['bin/arcanum', 'command:contact:submit']);
    }

    public function testCommandHandledFiresAfterSuccessfulDispatch(): void
    {
        // Arrange
        $capturedExitCode = null;
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->atLeastOnce())->method('dispatch')->willReturnCallback(
            function (object $event) use (&$capturedExitCode) {
                if ($event instanceof CommandHandled) {
                    $capturedExitCode = $event->getExitCode();
                }
                return $event;
            },
        );

        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')->willReturn(new EmptyDTO());

        $kernel = $this->bootstrapKernel($this->containerWith(
            bus: $bus,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
            eventDispatcher: $dispatcher,
        ));

        // Act
        $kernel->handle(['bin/arcanum', 'command:contact:submit']);

        // Assert
        $this->assertSame(ExitCode::Success->value, $capturedExitCode);
    }

    public function testCommandFailedFiresOnException(): void
    {
        // Arrange
        $capturedException = null;
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->atLeastOnce())->method('dispatch')->willReturnCallback(
            function (object $event) use (&$capturedException) {
                if ($event instanceof CommandFailed) {
                    $capturedException = $event->getException();
                }
                return $event;
            },
        );

        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException('boom'));

        $kernel = $this->bootstrapKernel($this->containerWith(
            bus: $bus,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
            eventDispatcher: $dispatcher,
        ));

        // Act
        $kernel->handle(['bin/arcanum', 'query:shop:products']);

        // Assert
        $this->assertInstanceOf(\RuntimeException::class, $capturedException);
    }

    public function testCommandCompletedFiresOnTerminate(): void
    {
        // Arrange
        $fired = false;
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->atLeastOnce())->method('dispatch')->willReturnCallback(
            function (object $event) use (&$fired) {
                if ($event instanceof CommandCompleted) {
                    $fired = true;
                }
                return $event;
            },
        );

        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')->willReturn(new EmptyDTO());

        $kernel = $this->bootstrapKernel($this->containerWith(
            bus: $bus,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
            eventDispatcher: $dispatcher,
        ));

        // Act
        $kernel->handle(['bin/arcanum', 'command:contact:submit']);
        $kernel->terminate();

        // Assert
        $this->assertTrue($fired);
    }

    public function testHandleWorksWithoutEventDispatcher(): void
    {
        // Arrange
        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')->willReturn(new EmptyDTO());

        $kernel = $this->bootstrapKernel($this->containerWith(
            bus: $bus,
            routeFixtureNamespace: 'Arcanum\\Test\\Fixture',
        ));

        // Act
        $exitCode = $kernel->handle(['bin/arcanum', 'command:contact:submit']);

        // Assert
        $this->assertSame(ExitCode::Success->value, $exitCode);
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
        EventDispatcherInterface|null $eventDispatcher = null,
        bool $withFormatRegistry = false,
        LoggerInterface|null $logger = null,
        \Arcanum\Quill\CorrelationProcessor|null $correlationProcessor = null,
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
                EventDispatcherInterface::class => $eventDispatcher !== null,
                CliFormatRegistry::class => $formatRegistry !== null,
                LoggerInterface::class => $logger !== null,
                \Arcanum\Quill\CorrelationProcessor::class => $correlationProcessor !== null,
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
                EventDispatcherInterface::class => $eventDispatcher ?? throw new \RuntimeException('No dispatcher'),
                CliFormatRegistry::class => $formatRegistry ?? throw new \RuntimeException('No registry'),
                LoggerInterface::class => $logger ?? throw new \RuntimeException('No logger'),
                \Arcanum\Quill\CorrelationProcessor::class =>
                    $correlationProcessor ?? throw new \RuntimeException('No processor'),
                default => throw new \RuntimeException("Unexpected service: $id"),
            },
        );

        return $container;
    }

    private function bootstrapKernel(Application $container): RuneKernel
    {
        // Use empty bootstrapper lists so tests don't need real config files.
        $kernel = new class ('/app') extends RuneKernel {
            /** @var class-string<Bootstrapper>[] */
            protected array $bootstrappers = [];
            /** @var class-string<Bootstrapper>[] */
            protected array $earlyBootstrappers = [];
        };

        $kernel->bootstrap($container);
        return $kernel;
    }

    // ---------------------------------------------------------------
    // Lifecycle logging
    // ---------------------------------------------------------------

    public function testHandleLogsCommandReceivedAndCompleted(): void
    {
        // Arrange
        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')->willReturn(new EmptyDTO());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with('Command received', ['command' => 'query:shop:products']);
        $logger->expects($this->once())
            ->method('info')
            ->with('Command completed', $this->callback(
                fn(array $ctx) => $ctx['command'] === 'query:shop:products'
                    && $ctx['exit_code'] === 0,
            ));

        $container = $this->containerWith(bus: $bus, logger: $logger);
        $kernel = $this->bootstrapKernel($container);

        // Act
        $kernel->handle(['bin/arcanum', 'query:shop:products']);
    }

    public function testHandleLogsCompletedOnFailure(): void
    {
        // Arrange — route to a command that throws
        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')->willThrowException(new \RuntimeException('Boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('debug');
        $logger->expects($this->once())
            ->method('info')
            ->with('Command completed', $this->callback(
                fn(array $ctx) => $ctx['exit_code'] === ExitCode::Failure->value,
            ));

        $container = $this->containerWith(bus: $bus, logger: $logger);
        $kernel = $this->bootstrapKernel($container);

        // Act
        $kernel->handle(['bin/arcanum', 'query:shop:products']);
    }

    public function testHandleSetsAndClearsCorrelationId(): void
    {
        // Arrange
        $bus = $this->createStub(Bus::class);
        $bus->method('dispatch')->willReturn(new EmptyDTO());

        $processor = new \Arcanum\Quill\CorrelationProcessor();

        $container = $this->containerWith(bus: $bus, correlationProcessor: $processor);
        $kernel = $this->bootstrapKernel($container);

        // Act
        $exitCode = $kernel->handle(['bin/arcanum', 'query:shop:products']);

        // Assert — correlation ID was set during handle and cleared afterward
        $this->assertSame(0, $exitCode);
        $this->assertNull(
            (new \ReflectionProperty($processor, 'correlationId'))->getValue($processor),
            'Correlation ID should be cleared after handle()',
        );
    }

    public function testHandleNoCommandSkipsLogging(): void
    {
        // Arrange — empty command shows splash, should not log
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('debug');
        $logger->expects($this->never())->method('info');

        $stdout = $this->createStream();
        $output = new ConsoleOutput($stdout, $this->createStream(), ansi: false);
        $container = $this->containerWith(output: $output, logger: $logger);
        $kernel = $this->bootstrapKernel($container);

        // Act
        $kernel->handle(['bin/arcanum']);
    }
}
