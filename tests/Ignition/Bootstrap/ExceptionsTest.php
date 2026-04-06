<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition\Bootstrap;

use Arcanum\Cabinet\Application;
use Arcanum\Cabinet\Container;
use Arcanum\Gather\Configuration;
use Arcanum\Glitch\ErrorHandler;
use Arcanum\Glitch\ExceptionHandler;
use Arcanum\Glitch\ShutdownHandler;
use Arcanum\Ignition\Bootstrap\Exceptions;
use Arcanum\Ignition\Kernel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Exceptions::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
final class ExceptionsTest extends TestCase
{
    protected function setUp(): void
    {
        Exceptions::$reservedMemory = null;
    }

    protected function tearDown(): void
    {
        restore_error_handler();
        restore_exception_handler();
        Exceptions::$reservedMemory = null;
    }

    /**
     * Build a stub Application that returns specific handlers.
     * Returns null from get() for unregistered handler interfaces.
     *
     * @param array<string, mixed> $appConfig
     */
    private function buildApplication(
        string $environment = 'production',
        ErrorHandler|null $errorHandler = null,
        ExceptionHandler|null $exceptionHandler = null,
        ShutdownHandler|null $shutdownHandler = null,
        array $appConfig = [],
    ): Application {
        $config = new Configuration(['app' => array_merge(['environment' => $environment], $appConfig)]);

        $app = $this->createStub(Application::class);
        $app->method('get')->willReturnCallback(
            fn(string $id) => match ($id) {
                Configuration::class => $config,
                ErrorHandler::class => $errorHandler,
                ExceptionHandler::class => $exceptionHandler,
                ShutdownHandler::class => $shutdownHandler,
                default => null,
            }
        );

        return $app;
    }

    // -----------------------------------------------------------
    // Bootstrap behavior
    // -----------------------------------------------------------

    public function testReservesMemoryOnBootstrap(): void
    {
        // Arrange
        $app = $this->buildApplication();
        $bootstrapper = new Exceptions();

        // Act
        $bootstrapper->bootstrap($app);

        // Assert — 32KB reserved
        $this->assertNotNull(Exceptions::$reservedMemory);
        $this->assertSame(32768, strlen(Exceptions::$reservedMemory));
    }

    public function testSetsErrorReportingToAll(): void
    {
        // Arrange
        $app = $this->buildApplication();
        $bootstrapper = new Exceptions();

        // Act
        $bootstrapper->bootstrap($app);

        // Assert
        $this->assertSame(-1, error_reporting());
    }

    public function testDisablesDisplayErrorsInProduction(): void
    {
        // Arrange
        $app = $this->buildApplication('production');
        $bootstrapper = new Exceptions();

        // Act
        $bootstrapper->bootstrap($app);

        // Assert
        $this->assertSame('Off', ini_get('display_errors'));
    }

    public function testKeepsDisplayErrorsInTestingEnvironment(): void
    {
        // Arrange
        ini_set('display_errors', 'On');
        $app = $this->buildApplication('testing');
        $bootstrapper = new Exceptions();

        // Act
        $bootstrapper->bootstrap($app);

        // Assert
        $this->assertNotSame('Off', ini_get('display_errors'));
    }

    // -----------------------------------------------------------
    // verbose_errors config
    // -----------------------------------------------------------

    public function testVerboseErrorsDefaultsToDebugWhenNotSet(): void
    {
        // Arrange
        $app = $this->buildApplication(appConfig: ['debug' => true]);
        $bootstrapper = new Exceptions();

        // Act
        $bootstrapper->bootstrap($app);

        // Assert
        /** @var Configuration $config */
        $config = $app->get(Configuration::class);
        $this->assertTrue($config->get('app.verbose_errors'));
    }

    public function testVerboseErrorsDefaultsToFalseWhenDebugIsFalse(): void
    {
        // Arrange
        $app = $this->buildApplication(appConfig: ['debug' => false]);
        $bootstrapper = new Exceptions();

        // Act
        $bootstrapper->bootstrap($app);

        // Assert
        /** @var Configuration $config */
        $config = $app->get(Configuration::class);
        $this->assertFalse($config->get('app.verbose_errors'));
    }

    public function testVerboseErrorsDefaultsToTrueWhenDebugIsStringTrue(): void
    {
        // Arrange
        $app = $this->buildApplication(appConfig: ['debug' => 'true']);
        $bootstrapper = new Exceptions();

        // Act
        $bootstrapper->bootstrap($app);

        // Assert
        /** @var Configuration $config */
        $config = $app->get(Configuration::class);
        $this->assertTrue($config->get('app.verbose_errors'));
    }

    public function testVerboseErrorsPreservesExplicitValue(): void
    {
        // Arrange — debug is false but verbose_errors is explicitly true
        $app = $this->buildApplication(appConfig: [
            'debug' => false,
            'verbose_errors' => true,
        ]);
        $bootstrapper = new Exceptions();

        // Act
        $bootstrapper->bootstrap($app);

        // Assert
        /** @var Configuration $config */
        $config = $app->get(Configuration::class);
        $this->assertTrue($config->get('app.verbose_errors'));
    }

    public function testVerboseErrorsCanBeDisabledWhileDebugIsOn(): void
    {
        // Arrange — debug is on but verbose_errors explicitly off
        $app = $this->buildApplication(appConfig: [
            'debug' => true,
            'verbose_errors' => false,
        ]);
        $bootstrapper = new Exceptions();

        // Act
        $bootstrapper->bootstrap($app);

        // Assert
        /** @var Configuration $config */
        $config = $app->get(Configuration::class);
        $this->assertFalse($config->get('app.verbose_errors'));
    }

    // -----------------------------------------------------------
    // handleError()
    // -----------------------------------------------------------

    public function testHandleErrorDelegatesToContainerHandler(): void
    {
        // Arrange
        $handler = $this->createMock(ErrorHandler::class);
        $handler->expects($this->once())
            ->method('handleError')
            ->with(E_WARNING, 'test warning', '/test.php', 42)
            ->willReturn(true);

        $app = $this->buildApplication(errorHandler: $handler);
        $bootstrapper = new Exceptions();
        $bootstrapper->bootstrap($app);

        // Act
        $result = $bootstrapper->handleError(E_WARNING, 'test warning', '/test.php', 42);

        // Assert
        $this->assertTrue($result);
    }

    public function testHandleErrorReturnsFalseWhenNoHandler(): void
    {
        // Arrange — no ErrorHandler registered
        $app = $this->buildApplication();
        $bootstrapper = new Exceptions();
        $bootstrapper->bootstrap($app);

        // Act
        $result = $bootstrapper->handleError(E_WARNING, 'test', '/test.php', 1);

        // Assert
        $this->assertFalse($result);
    }

    // -----------------------------------------------------------
    // handleException()
    // -----------------------------------------------------------

    public function testHandleExceptionDelegatesToContainerHandler(): void
    {
        // Arrange
        $exception = new \RuntimeException('test exception');

        $handler = $this->createMock(ExceptionHandler::class);
        $handler->expects($this->once())
            ->method('handleException')
            ->with($exception);

        $app = $this->buildApplication(exceptionHandler: $handler);
        $bootstrapper = new Exceptions();
        $bootstrapper->bootstrap($app);

        // Act
        $bootstrapper->handleException($exception);
    }

    public function testHandleExceptionFreesReservedMemory(): void
    {
        // Arrange — use a handler so we don't hit the error_log fallback
        $handler = $this->createStub(ExceptionHandler::class);
        $app = $this->buildApplication(exceptionHandler: $handler);
        $bootstrapper = new Exceptions();
        $bootstrapper->bootstrap($app);

        $this->assertNotNull(Exceptions::$reservedMemory);

        // Act
        $bootstrapper->handleException(new \RuntimeException('test'));

        // Assert
        $this->assertNull(Exceptions::$reservedMemory);
    }

    public function testHandleExceptionFallsToErrorLogWhenNoHandler(): void
    {
        // Arrange — no ExceptionHandler in container; redirect error_log to temp file
        $app = $this->buildApplication();
        $bootstrapper = new Exceptions();
        $bootstrapper->bootstrap($app);

        $tempLog = tempnam(sys_get_temp_dir(), 'arcanum_test_');
        $previousLog = ini_set('error_log', $tempLog);

        // Act
        $bootstrapper->handleException(new \RuntimeException('unhandled'));

        // Restore
        ini_set('error_log', $previousLog !== false ? $previousLog : '');

        // Assert — the exception message was logged
        $logContents = file_get_contents($tempLog);
        unlink($tempLog);
        $this->assertIsString($logContents);
        $this->assertStringContainsString('unhandled', $logContents);
    }

    // -----------------------------------------------------------
    // handleShutdown()
    // -----------------------------------------------------------

    public function testHandleShutdownDelegatesToContainerHandler(): void
    {
        // Arrange
        $handler = $this->createMock(ShutdownHandler::class);
        $handler->expects($this->once())
            ->method('handleShutdown');

        $app = $this->buildApplication(shutdownHandler: $handler);
        $bootstrapper = new Exceptions();
        $bootstrapper->bootstrap($app);

        // Act
        $bootstrapper->handleShutdown();
    }

    public function testHandleShutdownFreesReservedMemory(): void
    {
        // Arrange
        $handler = $this->createStub(ShutdownHandler::class);
        $app = $this->buildApplication(shutdownHandler: $handler);
        $bootstrapper = new Exceptions();
        $bootstrapper->bootstrap($app);

        $this->assertNotNull(Exceptions::$reservedMemory);

        // Act
        $bootstrapper->handleShutdown();

        // Assert
        $this->assertNull(Exceptions::$reservedMemory);
    }

    public function testHandleShutdownDoesNothingWhenNoHandler(): void
    {
        // Arrange — no ShutdownHandler registered
        $app = $this->buildApplication();
        $bootstrapper = new Exceptions();
        $bootstrapper->bootstrap($app);

        // Act — should not throw
        $bootstrapper->handleShutdown();

        // Assert
        $this->addToAssertionCount(1);
    }
}
