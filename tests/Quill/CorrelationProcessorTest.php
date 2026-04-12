<?php

declare(strict_types=1);

namespace Arcanum\Test\Quill;

use Arcanum\Quill\CorrelationProcessor;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CorrelationProcessor::class)]
final class CorrelationProcessorTest extends TestCase
{
    private function makeRecord(string $message = 'test'): LogRecord
    {
        return new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: $message,
        );
    }

    public function testNoOpWhenCorrelationIdNotSet(): void
    {
        // Arrange
        $processor = new CorrelationProcessor();
        $record = $this->makeRecord();

        // Act
        $result = $processor($record);

        // Assert
        $this->assertSame($record, $result);
        $this->assertArrayNotHasKey('correlation_id', $result->extra);
    }

    public function testAddsCorrelationIdToExtra(): void
    {
        // Arrange
        $processor = new CorrelationProcessor();
        $processor->setCorrelationId('abc123');
        $record = $this->makeRecord();

        // Act
        $result = $processor($record);

        // Assert
        $this->assertSame('abc123', $result->extra['correlation_id']);
    }

    public function testPreservesExistingExtra(): void
    {
        // Arrange
        $processor = new CorrelationProcessor();
        $processor->setCorrelationId('abc123');
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            extra: ['existing' => 'value'],
        );

        // Act
        $result = $processor($record);

        // Assert
        $this->assertSame('value', $result->extra['existing']);
        $this->assertSame('abc123', $result->extra['correlation_id']);
    }

    public function testClearCorrelationIdStopsAddingIt(): void
    {
        // Arrange
        $processor = new CorrelationProcessor();
        $processor->setCorrelationId('abc123');
        $processor->clearCorrelationId();
        $record = $this->makeRecord();

        // Act
        $result = $processor($record);

        // Assert
        $this->assertSame($record, $result);
        $this->assertArrayNotHasKey('correlation_id', $result->extra);
    }

    public function testCorrelationIdCanBeChanged(): void
    {
        // Arrange
        $processor = new CorrelationProcessor();
        $processor->setCorrelationId('first');
        $record1 = $this->makeRecord('first unit');

        // Act
        $result1 = $processor($record1);
        $processor->setCorrelationId('second');
        $result2 = $processor($this->makeRecord('second unit'));

        // Assert
        $this->assertSame('first', $result1->extra['correlation_id']);
        $this->assertSame('second', $result2->extra['correlation_id']);
    }
}
