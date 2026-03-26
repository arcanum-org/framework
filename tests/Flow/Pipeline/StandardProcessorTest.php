<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Flow\Pipeline\StandardProcessor;
use Arcanum\Flow\Interrupted;
use Arcanum\Test\Fixture\Counter;
use Arcanum\Test\Fixture\Concatenator;

#[CoversClass(StandardProcessor::class)]
#[UsesClass(Interrupted::class)]
final class StandardProcessorTest extends TestCase
{
    public function testStandardProcessor(): void
    {
        // Arrange
        $proccessor = new StandardProcessor();
        $counter = new Counter();

        // Act
        $result = $proccessor->process(
            $counter,
            function (object $counter): object {
                if ($counter instanceof Counter) {
                    $counter->increment();
                }
                return $counter;
            },
            function (object $counter): object {
                if ($counter instanceof Counter) {
                    $counter->increment();
                }
                return $counter;
            }
        );

        // Assert
        $this->assertSame($counter, $result);
        $this->assertEquals(2, $counter->count());
    }

    public function testReturningExecutesSameOrder(): void
    {
        // Arrange
        $proccessor = new StandardProcessor();
        $concatenator = new Concatenator();

        // Act
        $result = $proccessor->process(
            $concatenator,
            function (object $payload): object {
                if ($payload instanceof Concatenator) {
                    $payload->add('a');
                }
                return $payload;
            },
            function (object $payload): object {
                if ($payload instanceof Concatenator) {
                    $payload->add('b');
                }
                return $payload;
            },
            function (object $payload): object {
                if ($payload instanceof Concatenator) {
                    $payload->add('c');
                }
                return $payload;
            }
        );

        // Assert
        $this->assertSame($concatenator, $result);
        $this->assertEquals('abc', (string)$concatenator);
    }

    public function testNotReturningPayloadInterruptsFlow(): void
    {
        // Arrange
        $proccessor = new StandardProcessor();
        $concatenator = new Concatenator();

        // Act

        $result = null;
        try {
            $result = $proccessor->process(
                $concatenator,
                function (object $payload): object {
                    if ($payload instanceof Concatenator) {
                        $payload->add('a');
                    }
                    return $payload;
                },
                function (object $payload): object {
                    if ($payload instanceof Concatenator) {
                        $payload->add('b');
                    }
                    return $payload;
                },
                function (object $payload): object|null {
                    if ($payload instanceof Concatenator) {
                        $payload->add('c');
                    }
                    return null;
                },
                function (object $payload): object {
                    if ($payload instanceof Concatenator) {
                        $payload->add('d');
                    }
                    return $payload;
                }
            );
        } catch (Interrupted $e) {
            // Assert
            $this->assertSame(null, $result);

            // never added "d"
            $this->assertEquals('abc', (string)$concatenator);
        }
    }
}
