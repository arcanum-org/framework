<?php

declare(strict_types=1);

namespace Arcanum\Test\Quill;

use Arcanum\Quill\Logger;
use Arcanum\Quill\Channel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Logger::class)]
#[UsesClass(Channel::class)]
#[UsesClass(\Monolog\Logger::class)]
final class LoggerTest extends TestCase
{
    public function testEmergency(): void
    {
        // Arrange
        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $defaultLogger = $this->createMock(\Monolog\Logger::class);

        $defaultLogger->expects($this->once())
            ->method('getName')
            ->willReturn('default');

        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $otherLogger = $this->createMock(\Monolog\Logger::class);

        $otherLogger->expects($this->once())
            ->method('getName')
            ->willReturn('other');


        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $defaultChannel = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$defaultLogger])
            ->getMock();

        $defaultChannel->expects($this->once())
            ->method('emergency')
            ->with('test');

        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $channelB = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$otherLogger])
            ->getMock();

        $channelB->expects($this->never())
            ->method('emergency');

        $logger = new Logger($defaultChannel, $channelB);

        // Act
        $logger->emergency('test');
    }

    public function testAlert(): void
    {
        // Arrange
        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $defaultLogger = $this->createMock(\Monolog\Logger::class);

        $defaultLogger->expects($this->once())
            ->method('getName')
            ->willReturn('default');

        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $otherLogger = $this->createMock(\Monolog\Logger::class);

        $otherLogger->expects($this->once())
            ->method('getName')
            ->willReturn('other');


        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $defaultChannel = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$defaultLogger])
            ->getMock();

        $defaultChannel->expects($this->once())
            ->method('alert')
            ->with('test');

        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $channelB = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$otherLogger])
            ->getMock();

        $channelB->expects($this->never())
            ->method('alert');

        $logger = new Logger($defaultChannel, $channelB);

        // Act
        $logger->alert('test');
    }

    public function testCritical(): void
    {
        // Arrange
        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $defaultLogger = $this->createMock(\Monolog\Logger::class);

        $defaultLogger->expects($this->once())
            ->method('getName')
            ->willReturn('default');

        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $otherLogger = $this->createMock(\Monolog\Logger::class);

        $otherLogger->expects($this->once())
            ->method('getName')
            ->willReturn('other');


        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $defaultChannel = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$defaultLogger])
            ->getMock();

        $defaultChannel->expects($this->once())
            ->method('critical')
            ->with('test');

        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $channelB = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$otherLogger])
            ->getMock();

        $channelB->expects($this->never())
            ->method('critical');

        $logger = new Logger($defaultChannel, $channelB);

        // Act
        $logger->critical('test');
    }

    public function testError(): void
    {
        // Arrange
        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $defaultLogger = $this->createMock(\Monolog\Logger::class);

        $defaultLogger->expects($this->once())
            ->method('getName')
            ->willReturn('default');

        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $otherLogger = $this->createMock(\Monolog\Logger::class);

        $otherLogger->expects($this->once())
            ->method('getName')
            ->willReturn('other');


        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $defaultChannel = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$defaultLogger])
            ->getMock();

        $defaultChannel->expects($this->once())
            ->method('error')
            ->with('test');

        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $channelB = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$otherLogger])
            ->getMock();

        $channelB->expects($this->never())
            ->method('error');

        $logger = new Logger($defaultChannel, $channelB);

        // Act
        $logger->error('test');
    }

    public function testWarning(): void
    {
        // Arrange
        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $defaultLogger = $this->createMock(\Monolog\Logger::class);

        $defaultLogger->expects($this->once())
            ->method('getName')
            ->willReturn('default');

        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $otherLogger = $this->createMock(\Monolog\Logger::class);

        $otherLogger->expects($this->once())
            ->method('getName')
            ->willReturn('other');


        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $defaultChannel = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$defaultLogger])
            ->getMock();

        $defaultChannel->expects($this->once())
            ->method('warning')
            ->with('test');

        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $channelB = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$otherLogger])
            ->getMock();

        $channelB->expects($this->never())
            ->method('warning');

        $logger = new Logger($defaultChannel, $channelB);

        // Act
        $logger->warning('test');
    }

    public function testNotice(): void
    {
        // Arrange
        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $defaultLogger = $this->createMock(\Monolog\Logger::class);

        $defaultLogger->expects($this->once())
            ->method('getName')
            ->willReturn('default');

        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $otherLogger = $this->createMock(\Monolog\Logger::class);

        $otherLogger->expects($this->once())
            ->method('getName')
            ->willReturn('other');


        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $defaultChannel = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$defaultLogger])
            ->getMock();

        $defaultChannel->expects($this->once())
            ->method('notice')
            ->with('test');

        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $channelB = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$otherLogger])
            ->getMock();

        $channelB->expects($this->never())
            ->method('notice');

        $logger = new Logger($defaultChannel, $channelB);

        // Act
        $logger->notice('test');
    }

    public function testInfo(): void
    {
        // Arrange
        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $defaultLogger = $this->createMock(\Monolog\Logger::class);

        $defaultLogger->expects($this->once())
            ->method('getName')
            ->willReturn('default');

        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $otherLogger = $this->createMock(\Monolog\Logger::class);

        $otherLogger->expects($this->once())
            ->method('getName')
            ->willReturn('other');


        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $defaultChannel = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$defaultLogger])
            ->getMock();

        $defaultChannel->expects($this->once())
            ->method('info')
            ->with('test');

        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $channelB = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$otherLogger])
            ->getMock();

        $channelB->expects($this->never())
            ->method('info');

        $logger = new Logger($defaultChannel, $channelB);

        // Act
        $logger->info('test');
    }

    public function testDebug(): void
    {
        // Arrange
        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $defaultLogger = $this->createMock(\Monolog\Logger::class);

        $defaultLogger->expects($this->once())
            ->method('getName')
            ->willReturn('default');

        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $otherLogger = $this->createMock(\Monolog\Logger::class);

        $otherLogger->expects($this->once())
            ->method('getName')
            ->willReturn('other');


        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $defaultChannel = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$defaultLogger])
            ->getMock();

        $defaultChannel->expects($this->once())
            ->method('debug')
            ->with('test');

        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $channelB = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$otherLogger])
            ->getMock();

        $channelB->expects($this->never())
            ->method('debug');

        $logger = new Logger($defaultChannel, $channelB);

        // Act
        $logger->debug('test');
    }

    public function testLog(): void
    {
        // Arrange
        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $defaultLogger = $this->createMock(\Monolog\Logger::class);

        $defaultLogger->expects($this->once())
            ->method('getName')
            ->willReturn('default');

        /** @var \Monolog\Logger&\PHPUnit\Framework\MockObject\MockObject */
        $otherLogger = $this->createMock(\Monolog\Logger::class);

        $otherLogger->expects($this->once())
            ->method('getName')
            ->willReturn('other');


        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $defaultChannel = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$defaultLogger])
            ->getMock();

        $defaultChannel->expects($this->once())
            ->method('log')
            ->with('debug', 'test');

        /** @var Channel&\PHPUnit\Framework\MockObject\MockObject */
        $channelB = $this->getMockBuilder(Channel::class)
            ->setConstructorArgs([$otherLogger])
            ->getMock();

        $channelB->expects($this->never())
            ->method('log');

        $logger = new Logger($defaultChannel, $channelB);

        // Act
        $logger->log('debug', 'test');
    }

    public function testChannel(): void
    {
        // Arrange
        $defaultLogger = $this->createStub(\Monolog\Logger::class);
        $defaultLogger->method('getName')->willReturn('default');

        $otherLogger = $this->createStub(\Monolog\Logger::class);
        $otherLogger->method('getName')->willReturn('other');

        $defaultChannel = new Channel($defaultLogger);
        $channelB = new Channel($otherLogger);

        $logger = new Logger($defaultChannel, $channelB);

        // Act
        $channel = $logger->channel('other');

        // Assert
        $this->assertSame($channelB, $channel);
    }

    public function testAddChannel(): void
    {
        // Arrange
        $defaultLogger = $this->createStub(\Monolog\Logger::class);
        $defaultLogger->method('getName')->willReturn('default');

        $otherLogger = $this->createStub(\Monolog\Logger::class);
        $otherLogger->method('getName')->willReturn('other');

        $defaultChannel = new Channel($defaultLogger);
        $channelB = new Channel($otherLogger);

        $logger = new Logger($defaultChannel);

        // Act
        $logger->addChannel($channelB);

        // Assert
        $this->assertSame($channelB, $logger->channel('other'));
    }

    public function testDefaulLoggerIfNoLoggerIsPassedIn(): void
    {
        // Arrange

        $logger = new Logger();

        // Act
        $channel = $logger->channel('default');

        // Assert
        $this->assertInstanceOf(Channel::class, $channel);
        $this->assertSame('default', $channel->name);
    }
}
