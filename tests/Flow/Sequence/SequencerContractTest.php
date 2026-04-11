<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Sequence;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Arcanum\Flow\Sequence\CloseLatch;
use Arcanum\Flow\Sequence\Cursor;
use Arcanum\Flow\Sequence\Sequencer;
use Arcanum\Flow\Sequence\Series;

#[CoversClass(Cursor::class)]
#[CoversClass(Series::class)]
#[CoversClass(CloseLatch::class)]
final class SequencerContractTest extends TestCase
{
    /**
     * @return iterable<string, array{\Closure(list<int>): Sequencer<int>}>
     */
    public static function shapes(): iterable
    {
        yield 'Series' => [
            static fn(array $items): Sequencer => new Series(array_values($items)),
        ];
        yield 'Cursor' => [
            static fn(array $items): Sequencer => Cursor::open(
                static function () use ($items): \Generator {
                    yield from array_values($items);
                },
                static function (): void {
                },
            ),
        ];
    }

    /**
     * @param \Closure(list<int>): Sequencer<int> $make
     */
    #[DataProvider('shapes')]
    public function testFirstReturnsLeadingElement(\Closure $make): void
    {
        $sequencer = $make([10, 20, 30]);

        $this->assertSame(10, $sequencer->first());
    }

    /**
     * @param \Closure(list<int>): Sequencer<int> $make
     */
    #[DataProvider('shapes')]
    public function testFirstReturnsNullOnEmpty(\Closure $make): void
    {
        $sequencer = $make([]);

        $this->assertNull($sequencer->first());
    }

    /**
     * @param \Closure(list<int>): Sequencer<int> $make
     */
    #[DataProvider('shapes')]
    public function testEachVisitsEveryItemInOrder(\Closure $make): void
    {
        $sequencer = $make([1, 2, 3]);

        $seen = [];
        $sequencer->each(function (int $item) use (&$seen): void {
            $seen[] = $item;
        });

        $this->assertSame([1, 2, 3], $seen);
    }

    /**
     * @param \Closure(list<int>): Sequencer<int> $make
     */
    #[DataProvider('shapes')]
    public function testMapTransformsEveryItem(\Closure $make): void
    {
        $sequencer = $make([1, 2, 3]);

        $mapped = $sequencer->map(static fn(int $n): int => $n * 10);

        $this->assertSame([10, 20, 30], $mapped->toSeries()->all());
    }

    /**
     * @param \Closure(list<int>): Sequencer<int> $make
     */
    #[DataProvider('shapes')]
    public function testFilterKeepsMatchingItems(\Closure $make): void
    {
        $sequencer = $make([1, 2, 3, 4, 5]);

        $filtered = $sequencer->filter(static fn(int $n): bool => $n % 2 === 0);

        $this->assertSame([2, 4], $filtered->toSeries()->all());
    }

    /**
     * @param \Closure(list<int>): Sequencer<int> $make
     */
    #[DataProvider('shapes')]
    public function testChunkGroupsItems(\Closure $make): void
    {
        $sequencer = $make([1, 2, 3, 4, 5]);

        $chunked = $sequencer->chunk(2)->toSeries()->all();

        $this->assertSame([[1, 2], [3, 4], [5]], $chunked);
    }

    /**
     * @param \Closure(list<int>): Sequencer<int> $make
     */
    #[DataProvider('shapes')]
    public function testTakeLimitsCount(\Closure $make): void
    {
        $sequencer = $make([1, 2, 3, 4, 5]);

        $taken = $sequencer->take(3)->toSeries()->all();

        $this->assertSame([1, 2, 3], $taken);
    }

    /**
     * @param \Closure(list<int>): Sequencer<int> $make
     */
    #[DataProvider('shapes')]
    public function testTakeZeroReturnsEmpty(\Closure $make): void
    {
        $sequencer = $make([1, 2, 3]);

        $taken = $sequencer->take(0)->toSeries()->all();

        $this->assertSame([], $taken);
    }

    /**
     * @param \Closure(list<int>): Sequencer<int> $make
     */
    #[DataProvider('shapes')]
    public function testToSeriesYieldsSeries(\Closure $make): void
    {
        $sequencer = $make([1, 2, 3]);

        $series = $sequencer->toSeries();

        $this->assertInstanceOf(Series::class, $series);
        $this->assertSame([1, 2, 3], $series->all());
    }

    /**
     * @param \Closure(list<int>): Sequencer<int> $make
     */
    #[DataProvider('shapes')]
    public function testForeachIterates(\Closure $make): void
    {
        $sequencer = $make([1, 2, 3]);

        $collected = [];
        foreach ($sequencer as $item) {
            $collected[] = $item;
        }

        $this->assertSame([1, 2, 3], $collected);
    }
}
