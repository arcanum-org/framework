<?php

declare(strict_types=1);

namespace Arcanum\Test\Glitch;

use Arcanum\Glitch\Handler;
use Arcanum\Glitch\Level;
use Arcanum\Glitch\Reporter;
use Arcanum\Cabinet\Application;
use Arcanum\Quill\ChannelLogger;
use Arcanum\Quill\Channel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Handler::class)]
#[UsesClass(Level::class)]
final class HandlerTest extends TestCase
{
    private function createHandler(
        ChannelLogger|null $logger = null,
        Application|null $container = null,
    ): Handler {
        return new Handler(
            $logger ?? $this->createStub(ChannelLogger::class),
            $container ?? $this->createStub(Application::class),
        );
    }

    // -----------------------------------------------------------
    // handleError() — error→exception conversion
    // -----------------------------------------------------------

    public function testHandleErrorThrowsErrorExceptionWhenReported(): void
    {
        // Arrange
        $handler = $this->createHandler();
        $originalReporting = error_reporting();
        error_reporting(\E_ALL & ~\E_DEPRECATED);

        try {
            // Assert
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessage('Test error');

            // Act
            $handler->handleError(\E_WARNING, 'Test error', '/file.php', 42);
        } finally {
            error_reporting($originalReporting);
        }
    }

    public function testHandleErrorReturnsFalseWhenNotReported(): void
    {
        // Arrange
        $handler = $this->createHandler();
        $originalReporting = error_reporting();
        error_reporting(0);

        try {
            // Act
            $result = $handler->handleError(\E_WARNING, 'Suppressed error', '/file.php', 42);

            // Assert
            $this->assertFalse($result);
        } finally {
            error_reporting($originalReporting);
        }
    }

    // -----------------------------------------------------------
    // handleError() — deprecation handling
    // -----------------------------------------------------------

    public function testHandleDeprecationLogsToDeprecationsChannel(): void
    {
        // Arrange
        $channel = $this->createMock(Channel::class);
        $channel->expects($this->once())
            ->method('warning')
            ->with(
                'Deprecated function',
                $this->callback(fn(array $ctx) =>
                    $ctx['errno'] === \E_DEPRECATED
                    && $ctx['errfile'] === '/file.php'
                    && $ctx['errline'] === 10)
            );

        $logger = $this->createMock(ChannelLogger::class);
        $logger->expects($this->once())
            ->method('channel')
            ->with('deprecations')
            ->willReturn($channel);

        $handler = $this->createHandler(logger: $logger);

        // Act
        $result = $handler->handleError(\E_DEPRECATED, 'Deprecated function', '/file.php', 10);

        // Assert
        $this->assertTrue($result);
    }

    public function testHandleUserDeprecationLogsToDeprecationsChannel(): void
    {
        // Arrange
        $channel = $this->createMock(Channel::class);
        $channel->expects($this->once())->method('warning');

        $logger = $this->createMock(ChannelLogger::class);
        $logger->expects($this->once())->method('channel')->with('deprecations')->willReturn($channel);

        $handler = $this->createHandler(logger: $logger);

        // Act
        $result = $handler->handleError(\E_USER_DEPRECATED, 'User deprecated', '/file.php', 5);

        // Assert
        $this->assertTrue($result);
    }

    public function testHandleDeprecationFallsBackToHandleExceptionOnLoggerFailure(): void
    {
        // Arrange — deprecations channel throws, fallback calls handleException
        // which logs (also fails) and then reports. Reporter is the proof
        // that the fallback path was reached.
        $reporter = $this->createMock(Reporter::class);
        $reporter->method('handles')->willReturn(true);
        $reporter->expects($this->once())
            ->method('__invoke')
            ->with($this->isInstanceOf(\ErrorException::class));

        $container = $this->createMock(Application::class);
        $container->expects($this->once())->method('get')->willReturn($reporter);

        $logger = $this->createStub(ChannelLogger::class);
        $logger->method('channel')->willThrowException(
            new \RuntimeException('Deprecations channel broken')
        );

        $handler = $this->createHandler(logger: $logger, container: $container);
        $handler->registerReporter(Reporter::class);

        // Redirect error_log to temp file because the new "always log first"
        // path causes logException to fail and fall back to error_log.
        $tempLog = tempnam(sys_get_temp_dir(), 'glitch_test_');
        $originalLog = ini_set('error_log', (string) $tempLog);

        try {
            // Act
            $result = $handler->handleError(\E_DEPRECATED, 'Deprecated', '/file.php', 1);

            // Assert
            $this->assertTrue($result);
        } finally {
            ini_set('error_log', (string) $originalLog);
            @unlink((string) $tempLog);
        }
    }

    // -----------------------------------------------------------
    // handleException() — reporter dispatch
    // -----------------------------------------------------------

    public function testHandleExceptionDispatchesToRegisteredReporters(): void
    {
        // Arrange
        $reporter = $this->createMock(Reporter::class);
        $reporter->method('handles')->willReturn(true);
        $reporter->expects($this->once())
            ->method('__invoke')
            ->with($this->isInstanceOf(\RuntimeException::class));

        $container = $this->createMock(Application::class);
        $container->expects($this->once())
            ->method('get')
            ->with(Reporter::class)
            ->willReturn($reporter);

        $handler = $this->createHandler(container: $container);
        $handler->registerReporter(Reporter::class);

        // Act
        $handler->handleException(new \RuntimeException('Test'));
    }

    public function testHandleExceptionSkipsReporterThatDoesNotHandle(): void
    {
        // Arrange
        $reporter = $this->createMock(Reporter::class);
        $reporter->method('handles')->willReturn(false);
        $reporter->expects($this->never())->method('__invoke');

        $container = $this->createMock(Application::class);
        $container->expects($this->once())->method('get')->willReturn($reporter);

        $handler = $this->createHandler(container: $container);
        $handler->registerReporter(Reporter::class);

        // Act
        $handler->handleException(new \RuntimeException('Test'));
    }

    public function testHandleExceptionLogsBothOriginalAndReporterFailure(): void
    {
        // Arrange — with the "always log first" policy, the original
        // exception is logged BEFORE reporters run. If a reporter then
        // throws, that failure is also logged. Both must be visible.
        $reporter = $this->createMock(Reporter::class);
        $reporter->expects($this->once())->method('handles')->willReturn(true);
        $reporter->expects($this->once())->method('__invoke')
            ->willThrowException(new \RuntimeException('Reporter broke'));

        $container = $this->createMock(Application::class);
        $container->expects($this->once())->method('get')->willReturn($reporter);

        $channel = $this->createMock(Channel::class);
        $channel->expects($this->exactly(2))
            ->method('critical')
            ->with(
                $this->callback(fn(string $msg) =>
                    $msg === 'Original error' || $msg === 'Reporter broke'),
                $this->anything(),
            );

        $logger = $this->createMock(ChannelLogger::class);
        $logger->expects($this->exactly(2))
            ->method('channel')
            ->with('default')
            ->willReturn($channel);

        $handler = $this->createHandler(logger: $logger, container: $container);
        $handler->registerReporter(Reporter::class);

        // Act
        $handler->handleException(new \RuntimeException('Original error'));
    }

    public function testHandleExceptionFallsBackToErrorLogWhenLoggerAlsoThrows(): void
    {
        // Arrange
        $reporter = $this->createMock(Reporter::class);
        $reporter->expects($this->once())->method('handles')->willReturn(true);
        $reporter->expects($this->once())->method('__invoke')
            ->willThrowException(new \RuntimeException('Reporter broke'));

        $container = $this->createMock(Application::class);
        $container->expects($this->once())->method('get')->willReturn($reporter);

        // Logger throws on every channel() call. The handler will attempt to
        // log twice: once for the original exception, once for the reporter
        // failure. Both fall through to error_log.
        $logger = $this->createMock(ChannelLogger::class);
        $logger->expects($this->exactly(2))->method('channel')
            ->willThrowException(new \RuntimeException('Logger also broke'));

        $handler = $this->createHandler(logger: $logger, container: $container);
        $handler->registerReporter(Reporter::class);

        // Redirect error_log output to a temp file to avoid test output
        $tempLog = tempnam(sys_get_temp_dir(), 'glitch_test_');
        $originalLog = ini_set('error_log', (string) $tempLog);

        try {
            // Act
            $handler->handleException(new \RuntimeException('Original error'));

            // Assert — error_log was called with the logger's exception message
            $logContents = file_get_contents((string) $tempLog);
            $this->assertIsString($logContents);
            $this->assertStringContainsString('Logger also broke', $logContents);
        } finally {
            ini_set('error_log', (string) $originalLog);
            @unlink((string) $tempLog);
        }
    }

    public function testHandleExceptionWithNoReportersDoesNotThrow(): void
    {
        // Arrange
        $handler = $this->createHandler();

        // Act — no reporters registered, should complete without error
        $handler->handleException(new \RuntimeException('No reporters'));

        // Assert — no exception means success
        $this->addToAssertionCount(1);
    }

    public function testHandleExceptionAlwaysLogsEvenWithNoReportersRegistered(): void
    {
        // Arrange — this is the regression test for the silent-swallow bug.
        // An exception thrown when zero reporters are wired up MUST still
        // produce a log entry, otherwise the framework eats errors silently.
        $channel = $this->createMock(Channel::class);
        $channel->expects($this->once())
            ->method('critical')
            ->with(
                'Cache key "::1" contains reserved characters',
                $this->callback(fn(array $ctx) => isset($ctx['exception'])),
            );

        $logger = $this->createMock(ChannelLogger::class);
        $logger->expects($this->once())
            ->method('channel')
            ->with('default')
            ->willReturn($channel);

        $handler = $this->createHandler(logger: $logger);

        // Act — no reporters registered at all
        $handler->handleException(
            new \RuntimeException('Cache key "::1" contains reserved characters'),
        );
    }

    public function testHandleExceptionLogsEvenWhenReporterAlsoHandles(): void
    {
        // Arrange — logging is the floor, not a fallback. Even when a
        // reporter accepts the exception, the log entry must still be
        // written so app developers always see errors in their log files.
        $reporter = $this->createMock(Reporter::class);
        $reporter->method('handles')->willReturn(true);
        $reporter->expects($this->once())->method('__invoke');

        $container = $this->createMock(Application::class);
        $container->expects($this->once())->method('get')->willReturn($reporter);

        $channel = $this->createMock(Channel::class);
        $channel->expects($this->once())
            ->method('critical')
            ->with('Reported and logged', $this->anything());

        $logger = $this->createMock(ChannelLogger::class);
        $logger->expects($this->once())
            ->method('channel')
            ->with('default')
            ->willReturn($channel);

        $handler = $this->createHandler(logger: $logger, container: $container);
        $handler->registerReporter(Reporter::class);

        // Act
        $handler->handleException(new \RuntimeException('Reported and logged'));
    }

    // -----------------------------------------------------------
    // registerReporter() and container resolution
    // -----------------------------------------------------------

    public function testRegisterReporterThrowsIfContainerReturnsNonReporter(): void
    {
        // Arrange — container returns non-Reporter, which triggers RuntimeException
        // in buildReporters, caught by handleException's fallback logger.
        // Two log calls: once for original exception, once for the buildReporters error.
        $container = $this->createMock(Application::class);
        $container->expects($this->once())->method('get')->willReturn(new \stdClass());

        $channel = $this->createMock(Channel::class);
        $channel->expects($this->exactly(2))->method('critical');

        $logger = $this->createMock(ChannelLogger::class);
        $logger->expects($this->exactly(2))->method('channel')->willReturn($channel);

        $handler = $this->createHandler(logger: $logger, container: $container);
        $handler->registerReporter(\stdClass::class); /** @phpstan-ignore argument.type */

        // Act
        $handler->handleException(new \RuntimeException('Test'));
    }

    // -----------------------------------------------------------
    // __invoke() — Reporter interface
    // -----------------------------------------------------------

    public function testInvokeDispatchesToReporters(): void
    {
        // Arrange
        $reporter = $this->createMock(Reporter::class);
        $reporter->method('handles')->willReturn(true);
        $reporter->expects($this->once())->method('__invoke');

        $container = $this->createMock(Application::class);
        $container->expects($this->once())->method('get')->willReturn($reporter);

        $handler = $this->createHandler(container: $container);
        $handler->registerReporter(Reporter::class);

        // Act
        $handler(new \RuntimeException('Via invoke'));
    }

    // -----------------------------------------------------------
    // handles() — Reporter interface
    // -----------------------------------------------------------

    public function testHandlesReturnsTrueForAllExceptions(): void
    {
        // Arrange
        $handler = $this->createHandler();

        // Assert
        $this->assertTrue($handler->handles(\RuntimeException::class));
        $this->assertTrue($handler->handles(\Throwable::class));
        $this->assertTrue($handler->handles(\InvalidArgumentException::class));
    }
}
