<?php

declare(strict_types=1);

namespace Arcanum\Test\Glitch;

use Arcanum\Glitch\LogReporter;
use Arcanum\Quill\ChannelLogger;
use Arcanum\Quill\Channel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LogReporter::class)]
final class LogReporterTest extends TestCase
{
    /**
     * @param non-empty-string $expectedLevel
     */
    private function createLogger(
        string $expectedChannel,
        string $expectedLevel,
        string $expectedMessage,
    ): ChannelLogger {
        $channel = $this->createMock(Channel::class);
        $channel->expects($this->once())
            ->method($expectedLevel)
            ->with(
                $this->identicalTo($expectedMessage),
                $this->callback(fn(array $ctx) => $ctx['exception'] instanceof \Throwable)
            );

        $logger = $this->createMock(ChannelLogger::class);
        $logger->expects($this->once())
            ->method('channel')
            ->with($expectedChannel)
            ->willReturn($channel);

        return $logger;
    }

    public function testReportsToDefaultChannelAtErrorLevel(): void
    {
        // Arrange
        $logger = $this->createLogger('default', 'error', 'Something broke');
        $reporter = new LogReporter($logger);

        // Act
        $reporter(new \RuntimeException('Something broke'));
    }

    public function testHandlesAllExceptions(): void
    {
        // Arrange
        $logger = $this->createStub(ChannelLogger::class);
        $reporter = new LogReporter($logger);

        // Assert
        $this->assertTrue($reporter->handles(\RuntimeException::class));
        $this->assertTrue($reporter->handles(\InvalidArgumentException::class));
        $this->assertTrue($reporter->handles(\Throwable::class));
    }

    public function testUsesCustomLevelForExceptionType(): void
    {
        // Arrange
        $logger = $this->createLogger('default', 'critical', 'DB down');
        $reporter = new LogReporter(
            $logger,
            levels: [\RuntimeException::class => 'critical'],
        );

        // Act
        $reporter(new \RuntimeException('DB down'));
    }

    public function testUsesCustomChannelForExceptionType(): void
    {
        // Arrange
        $logger = $this->createLogger('alerts', 'error', 'Alert!');
        $reporter = new LogReporter(
            $logger,
            channels: [\RuntimeException::class => 'alerts'],
        );

        // Act
        $reporter(new \RuntimeException('Alert!'));
    }

    public function testUsesCustomLevelAndChannel(): void
    {
        // Arrange
        $logger = $this->createLogger('security', 'emergency', 'Breach');
        $reporter = new LogReporter(
            $logger,
            levels: [\RuntimeException::class => 'emergency'],
            channels: [\RuntimeException::class => 'security'],
        );

        // Act
        $reporter(new \RuntimeException('Breach'));
    }

    public function testMatchesParentExceptionClass(): void
    {
        // Arrange — InvalidArgumentException extends LogicException
        // Map LogicException to 'warning' on 'logic' channel
        $logger = $this->createLogger('logic', 'warning', 'Bad argument');
        $reporter = new LogReporter(
            $logger,
            levels: [\LogicException::class => 'warning'],
            channels: [\LogicException::class => 'logic'],
        );

        // Act — InvalidArgumentException is an instanceof LogicException
        $reporter(new \InvalidArgumentException('Bad argument'));
    }

    public function testFallsBackToErrorLevelWhenNoMatch(): void
    {
        // Arrange — levels map only has RuntimeException, but we throw LogicException
        $logger = $this->createLogger('default', 'error', 'Unmatched');
        $reporter = new LogReporter(
            $logger,
            levels: [\RuntimeException::class => 'critical'],
        );

        // Act
        $reporter(new \LogicException('Unmatched'));
    }

    public function testFallsBackToDefaultChannelWhenNoMatch(): void
    {
        // Arrange — channels map only has RuntimeException, but we throw LogicException
        $logger = $this->createLogger('default', 'error', 'Unmatched');
        $reporter = new LogReporter(
            $logger,
            channels: [\RuntimeException::class => 'alerts'],
        );

        // Act
        $reporter(new \LogicException('Unmatched'));
    }
}
