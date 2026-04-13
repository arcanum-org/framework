<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition\Bootstrap;

use Arcanum\Cabinet\Container;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrap\Logger;
use Arcanum\Ignition\Kernel;
use Arcanum\Quill\ChannelLogger;
use Arcanum\Quill\Channel;
use Arcanum\Quill\Handler;
use Arcanum\Quill\Logger as QuillLogger;
use Arcanum\Quill\CorrelationProcessor;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Log\LoggerInterface;

#[CoversClass(Logger::class)]
#[UsesClass(Container::class)]
#[UsesClass(\Arcanum\Cabinet\SimpleProvider::class)]
#[UsesClass(\Arcanum\Cabinet\PrototypeProvider::class)]
#[UsesClass(\Arcanum\Codex\Resolver::class)]
#[UsesClass(\Arcanum\Codex\Event\ClassRequested::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
#[UsesClass(QuillLogger::class)]
#[UsesClass(Channel::class)]
#[UsesClass(CorrelationProcessor::class)]
final class LoggerTest extends TestCase
{
    private string $filesDir;

    protected function setUp(): void
    {
        $this->filesDir = sys_get_temp_dir() . '/arcanum_logger_test_' . uniqid();
        mkdir($this->filesDir . '/logs', 0777, true);
    }

    protected function tearDown(): void
    {
        // Clean up log files
        $logsDir = $this->filesDir . '/logs';
        if (is_dir($logsDir)) {
            foreach (glob($logsDir . '/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($logsDir);
        }
        if (is_dir($this->filesDir)) {
            rmdir($this->filesDir);
        }
    }

    /**
     * @param array<string, mixed> $logConfig
     */
    private function buildContainer(array $logConfig): Container
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);

        $config = new Configuration(['log' => $logConfig]);
        $container->instance(Configuration::class, $config);

        $kernel = $this->createStub(Kernel::class);
        $kernel->method('filesDirectory')->willReturn($this->filesDir);
        $container->instance(Kernel::class, $kernel);

        return $container;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultLogConfig(): array
    {
        return [
            'handlers' => [
                'default' => [
                    'type' => Handler::STREAM,
                ],
            ],
            'channels' => [
                'default' => ['default'],
            ],
        ];
    }

    public function testRegistersQuillLoggerFactory(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultLogConfig());
        $bootstrapper = new Logger();

        // Act
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(QuillLogger::class));
    }

    public function testRegistersChannelLoggerService(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultLogConfig());
        $bootstrapper = new Logger();

        // Act
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(ChannelLogger::class));
    }

    public function testLoggerFactoryReturnsLoggerInterface(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultLogConfig());
        $bootstrapper = new Logger();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var LoggerInterface $logger */
        $logger = $container->get(QuillLogger::class);

        // Assert
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testStreamHandlerWithDefaults(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultLogConfig());
        $bootstrapper = new Logger();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var QuillLogger $logger */
        $logger = $container->get(QuillLogger::class);

        // Assert — logger should work without throwing
        $logger->info('test message');
        $this->assertTrue(file_exists($this->filesDir . '/logs/default.log'));
    }

    public function testStreamHandlerWithCustomPath(): void
    {
        // Arrange
        $config = [
            'handlers' => [
                'app' => [
                    'type' => Handler::STREAM,
                    'path' => 'logs/custom.log',
                ],
            ],
            'channels' => [
                'default' => ['app'],
            ],
        ];
        $container = $this->buildContainer($config);
        $bootstrapper = new Logger();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var QuillLogger $logger */
        $logger = $container->get(QuillLogger::class);
        $logger->info('test');

        // Assert
        $this->assertTrue(file_exists($this->filesDir . '/logs/custom.log'));
    }

    public function testErrorLogHandler(): void
    {
        // Arrange
        $config = [
            'handlers' => [
                'errors' => [
                    'type' => Handler::ERROR_LOG,
                ],
            ],
            'channels' => [
                'default' => ['errors'],
            ],
        ];
        $container = $this->buildContainer($config);
        $bootstrapper = new Logger();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var QuillLogger $logger */
        $logger = $container->get(QuillLogger::class);

        // Assert — should be a valid logger (ErrorLogHandler doesn't write to filesystem)
        $this->assertInstanceOf(QuillLogger::class, $logger);
    }

    public function testMultipleChannelsWithDifferentHandlers(): void
    {
        // Arrange
        $config = [
            'handlers' => [
                'file' => [
                    'type' => Handler::STREAM,
                    'path' => 'logs/app.log',
                ],
                'errors' => [
                    'type' => Handler::ERROR_LOG,
                ],
            ],
            'channels' => [
                'default' => ['file'],
                'error' => ['errors'],
            ],
        ];
        $container = $this->buildContainer($config);
        $bootstrapper = new Logger();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var QuillLogger $logger */
        $logger = $container->get(QuillLogger::class);

        // Assert — both channels should be accessible
        $this->assertInstanceOf(Channel::class, $logger->channel('default'));
        $this->assertInstanceOf(Channel::class, $logger->channel('error'));
    }

    public function testChannelWithMultipleHandlers(): void
    {
        // Arrange — use EMERGENCY level on error_log handler to avoid PHPUnit output capture
        $config = [
            'handlers' => [
                'file' => [
                    'type' => Handler::STREAM,
                    'path' => 'logs/app.log',
                ],
                'errors' => [
                    'type' => Handler::ERROR_LOG,
                    'level' => 'emergency',
                ],
            ],
            'channels' => [
                'default' => ['file', 'errors'],
            ],
        ];
        $container = $this->buildContainer($config);
        $bootstrapper = new Logger();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var QuillLogger $logger */
        $logger = $container->get(QuillLogger::class);

        // Assert — info goes to file handler, not error_log handler
        $logger->info('dual handler test');
        $this->assertTrue(file_exists($this->filesDir . '/logs/app.log'));
    }

    public function testBindsLoggerInterfaceToQuillLogger(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultLogConfig());
        $bootstrapper = new Logger();

        // Act
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(LoggerInterface::class));
        $this->assertInstanceOf(LoggerInterface::class, $container->get(LoggerInterface::class));
    }

    public function testRespectsExistingLoggerInterfaceBinding(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultLogConfig());
        $customLogger = $this->createStub(LoggerInterface::class);
        $container->instance(LoggerInterface::class, $customLogger);
        $bootstrapper = new Logger();

        // Act
        $bootstrapper->bootstrap($container);

        // Assert — should still be the custom logger, not QuillLogger
        $this->assertSame($customLogger, $container->get(LoggerInterface::class));
    }

    public function testRegistersCorrelationProcessor(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultLogConfig());
        $bootstrapper = new Logger();

        // Act
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(CorrelationProcessor::class));
        $this->assertInstanceOf(CorrelationProcessor::class, $container->get(CorrelationProcessor::class));
    }

    public function testCorrelationIdAppearsInLogOutput(): void
    {
        // Arrange
        $container = $this->buildContainer($this->defaultLogConfig());
        $bootstrapper = new Logger();
        $bootstrapper->bootstrap($container);

        /** @var CorrelationProcessor $processor */
        $processor = $container->get(CorrelationProcessor::class);
        $processor->setCorrelationId('test-corr-123');

        /** @var QuillLogger $logger */
        $logger = $container->get(QuillLogger::class);

        // Act
        $logger->info('correlation test');

        // Assert — the log file should contain the correlation ID
        $logContent = file_get_contents($this->filesDir . '/logs/default.log');
        $this->assertIsString($logContent);
        $this->assertStringContainsString('test-corr-123', $logContent);
    }

    public function testSyslogHandler(): void
    {
        // Arrange
        $config = [
            'handlers' => [
                'syslog' => [
                    'type' => Handler::SYSLOG,
                    'ident' => 'arcanum-test',
                ],
            ],
            'channels' => [
                'default' => ['syslog'],
            ],
        ];
        $container = $this->buildContainer($config);
        $bootstrapper = new Logger();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var QuillLogger $logger */
        $logger = $container->get(QuillLogger::class);

        // Assert
        $this->assertInstanceOf(QuillLogger::class, $logger);
    }
}
