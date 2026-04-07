<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Arcanum\Flow\Sequence\CloseLatch;
use Arcanum\Flow\Sequence\Cursor;
use Arcanum\Flow\Sequence\CursorAlreadyConsumed;

#[CoversClass(Cursor::class)]
#[CoversClass(CloseLatch::class)]
#[CoversClass(CursorAlreadyConsumed::class)]
final class CursorTest extends TestCase
{
    /**
     * @param list<int> $items
     * @param-out int $closeCount
     * @return Cursor<int>
     */
    private function makeCursor(array $items, ?int &$closeCount = null): Cursor
    {
        $closeCount = 0;
        return Cursor::open(
            static function () use ($items): \Generator {
                yield from $items;
            },
            static function () use (&$closeCount): void {
                $closeCount++;
            },
        );
    }

    public function testSourceNotInvokedUntilIteration(): void
    {
        $counter = new SourceInvocationCounter();
        $cursor = Cursor::open(
            static function () use ($counter): \Generator {
                $counter->count++;
                yield 1;
            },
            static function (): void {
            },
        );

        $this->assertSame(0, $counter->count);
        iterator_to_array($cursor);
        $this->assertSame(1, $counter->count);
    }

    public function testSecondIterationThrows(): void
    {
        $cursor = $this->makeCursor([1, 2]);

        iterator_to_array($cursor);

        $this->expectException(CursorAlreadyConsumed::class);
        iterator_to_array($cursor);
    }

    public function testCloseRunsAfterFullIteration(): void
    {
        $cursor = $this->makeCursor([1, 2, 3], $closeCount);

        iterator_to_array($cursor);

        $this->assertSame(1, $closeCount);
    }

    public function testCloseRunsAfterEarlyBreak(): void
    {
        $cursor = $this->makeCursor([1, 2, 3], $closeCount);

        foreach ($cursor as $item) {
            break;
        }

        $this->assertSame(1, $closeCount);
    }

    public function testCloseRunsWhenIterationThrows(): void
    {
        $closeCount = 0;
        $cursor = Cursor::open(
            static function (): \Generator {
                yield 1;
                yield 2;
            },
            static function () use (&$closeCount): void {
                $closeCount++;
            },
        );

        try {
            foreach ($cursor as $item) {
                throw new \RuntimeException('boom');
            }
        } catch (\RuntimeException) {
            // expected
        }

        $this->assertSame(1, $closeCount);
    }

    public function testCloseRunsOnDestructWhenNeverIterated(): void
    {
        $closeCount = 0;
        $cursor = Cursor::open(
            static function (): \Generator {
                yield 1;
            },
            static function () use (&$closeCount): void {
                $closeCount++;
            },
        );

        unset($cursor);

        $this->assertSame(1, $closeCount);
    }

    public function testCloseIsIdempotent(): void
    {
        $cursor = $this->makeCursor([1], $closeCount);

        iterator_to_array($cursor);
        $cursor->close();
        $cursor->close();

        $this->assertSame(1, $closeCount);
    }

    public function testFirstReturnsHeadAndCloses(): void
    {
        $cursor = $this->makeCursor([10, 20, 30], $closeCount);

        $this->assertSame(10, $cursor->first());
        $this->assertSame(1, $closeCount);
    }

    public function testFirstOnEmptyClosesAndReturnsNull(): void
    {
        $cursor = $this->makeCursor([], $closeCount);

        $this->assertNull($cursor->first());
        $this->assertSame(1, $closeCount);
    }

    public function testFirstAfterIterationThrows(): void
    {
        $cursor = $this->makeCursor([1]);
        iterator_to_array($cursor);

        $this->expectException(CursorAlreadyConsumed::class);
        $cursor->first();
    }

    public function testToSeriesMaterializesAndCloses(): void
    {
        $cursor = $this->makeCursor([1, 2, 3], $closeCount);

        $series = $cursor->toSeries();

        $this->assertSame([1, 2, 3], $series->all());
        $this->assertSame(1, $closeCount);
    }

    public function testToSeriesAfterIterationThrows(): void
    {
        $cursor = $this->makeCursor([1]);
        iterator_to_array($cursor);

        $this->expectException(CursorAlreadyConsumed::class);
        $cursor->toSeries();
    }

    public function testMapIsLazyUntilTerminal(): void
    {
        $counter = new SourceInvocationCounter();
        /** @var Cursor<int> $cursor */
        $cursor = Cursor::open(
            static function () use ($counter): \Generator {
                $counter->count++;
                yield 1;
                yield 2;
            },
            static function (): void {
            },
        );

        $mapped = $cursor->map(static fn(int $n): int => $n * 2);
        $this->assertSame(0, $counter->count);

        $this->assertSame([2, 4], $mapped->toSeries()->all());
        $this->assertSame(1, $counter->count);
    }

    public function testMapMarksParentConsumed(): void
    {
        $cursor = $this->makeCursor([1, 2]);
        $cursor->map(static fn(int $n): int => $n);

        $this->expectException(CursorAlreadyConsumed::class);
        iterator_to_array($cursor);
    }

    public function testFilterIsLazyAndChains(): void
    {
        $cursor = $this->makeCursor([1, 2, 3, 4]);

        $result = $cursor
            ->filter(static fn(int $n): bool => $n % 2 === 0)
            ->map(static fn(int $n): int => $n * 10)
            ->toSeries()
            ->all();

        $this->assertSame([20, 40], $result);
    }

    public function testChunkLazy(): void
    {
        $cursor = $this->makeCursor([1, 2, 3, 4, 5]);

        $chunks = $cursor->chunk(2)->toSeries()->all();

        $this->assertSame([[1, 2], [3, 4], [5]], $chunks);
    }

    public function testTakeStopsEarly(): void
    {
        $counter = new SourceInvocationCounter();
        /** @var Cursor<int> $cursor */
        $cursor = Cursor::open(
            static function () use ($counter): \Generator {
                foreach ([1, 2, 3, 4, 5] as $n) {
                    $counter->count++;
                    yield $n;
                }
            },
            static function (): void {
            },
        );

        $taken = $cursor->take(2)->toSeries()->all();

        $this->assertSame([1, 2], $taken);
        $this->assertSame(2, $counter->count);
    }

    public function testDerivedCursorClosesSharedLatchOnce(): void
    {
        $cursor = $this->makeCursor([1, 2], $closeCount);

        $mapped = $cursor->map(static fn(int $n): int => $n);
        iterator_to_array($mapped);

        unset($cursor, $mapped);

        $this->assertSame(1, $closeCount);
    }

    public function testChunkRejectsZeroSize(): void
    {
        $cursor = $this->makeCursor([1]);

        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore argument.type */
        $cursor->chunk(0);
    }

    public function testTakeRejectsNegativeCount(): void
    {
        $cursor = $this->makeCursor([1]);

        $this->expectException(\InvalidArgumentException::class);
        /** @phpstan-ignore argument.type */
        $cursor->take(-1);
    }
}
