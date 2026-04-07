<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use Arcanum\Flow\Sequence\CloseLatch;
use Arcanum\Flow\Sequence\Cursor;
use Arcanum\Flow\Sequence\Sequencer;
use Arcanum\Flow\Sequence\Series;
use Arcanum\Forge\PdoConnection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdoConnection::class)]
#[UsesClass(Cursor::class)]
#[UsesClass(Series::class)]
#[UsesClass(CloseLatch::class)]
final class PdoConnectionQueryTest extends TestCase
{
    private function sqlite(): PdoConnection
    {
        return new PdoConnection(dsn: 'sqlite::memory:');
    }

    private function seeded(int $rows = 10): PdoConnection
    {
        $conn = $this->sqlite();
        $conn->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT)');
        for ($i = 1; $i <= $rows; $i++) {
            $conn->execute(
                'INSERT INTO items (name) VALUES (:name)',
                ['name' => "item-{$i}"],
            );
        }
        return $conn;
    }

    public function testQueryReturnsSequencer(): void
    {
        $conn = $this->seeded(3);

        $result = $conn->query('SELECT id, name FROM items ORDER BY id');

        $this->assertInstanceOf(Sequencer::class, $result);
    }

    public function testQueryStreamsRows(): void
    {
        $conn = $this->seeded(3);

        $rows = $conn->query('SELECT id, name FROM items ORDER BY id')
            ->toSeries()
            ->all();

        $this->assertCount(3, $rows);
        $this->assertSame('item-1', $rows[0]['name']);
        $this->assertSame('item-2', $rows[1]['name']);
        $this->assertSame('item-3', $rows[2]['name']);
    }

    public function testQueryBindsNamedParameters(): void
    {
        $conn = $this->sqlite();
        $conn->execute('CREATE TABLE items (id INTEGER PRIMARY KEY, name TEXT, color TEXT)');
        $conn->execute(
            'INSERT INTO items (name, color) VALUES (:name, :color)',
            ['name' => 'Widget', 'color' => 'blue'],
        );
        $conn->execute(
            'INSERT INTO items (name, color) VALUES (:name, :color)',
            ['name' => 'Gadget', 'color' => 'red'],
        );

        $row = $conn->query(
            'SELECT name FROM items WHERE color = :color',
            ['color' => 'blue'],
        )->first();

        $this->assertNotNull($row);
        $this->assertSame('Widget', $row['name']);
    }

    public function testQueryFirstOnEmptyReturnsNull(): void
    {
        $conn = $this->seeded(0);

        $row = $conn->query('SELECT id FROM items')->first();

        $this->assertNull($row);
    }

    public function testForeachIteratesEveryRow(): void
    {
        $conn = $this->seeded(5);

        $names = [];
        foreach ($conn->query('SELECT name FROM items ORDER BY id') as $row) {
            $names[] = $row['name'];
        }

        $this->assertSame(
            ['item-1', 'item-2', 'item-3', 'item-4', 'item-5'],
            $names,
        );
    }

    public function testStreamingMemoryIsFlatRelativeToRowCount(): void
    {
        // Guards against a regression where query() silently materializes.
        $conn = $this->seeded(2000);

        gc_collect_cycles();
        $before = memory_get_usage();

        $count = 0;
        foreach ($conn->query('SELECT id, name FROM items') as $row) {
            $count++;
        }

        $delta = memory_get_usage() - $before;

        $this->assertSame(2000, $count);
        // A fully materialized 2000-row result of 20+ bytes/row is ~100 KB+.
        // Streaming should stay well under that ceiling.
        $this->assertLessThan(
            32 * 1024,
            $delta,
            'Streaming query allocated more than 32 KB over 2000 rows',
        );
    }

    public function testCursorClosesAfterFullIteration(): void
    {
        $conn = $this->seeded(3);

        $cursor = $conn->query('SELECT id FROM items');
        iterator_to_array($cursor);

        // Second iteration must throw — the connection's cursor is now closed
        // and the Flow\Sequence contract forbids reuse.
        $this->expectException(\Arcanum\Flow\Sequence\CursorAlreadyConsumed::class);
        iterator_to_array($cursor);
    }

    public function testCursorClosesOnEarlyBreak(): void
    {
        $conn = $this->seeded(5);

        $cursor = $conn->query('SELECT id FROM items');

        foreach ($cursor as $row) {
            break;
        }

        // After break, the cursor's finally block should have run closeCursor.
        // A new query on the same connection must still work.
        $followUp = $conn->query('SELECT COUNT(*) AS c FROM items')->first();
        $this->assertNotNull($followUp);
        $count = $followUp['c'];
        $this->assertIsInt($count);
        $this->assertSame(5, $count);
    }

    public function testCursorClosesWhenIterationThrows(): void
    {
        $conn = $this->seeded(5);

        $cursor = $conn->query('SELECT id FROM items');

        try {
            foreach ($cursor as $row) {
                throw new \RuntimeException('boom');
            }
        } catch (\RuntimeException) {
            // expected
        }

        // Follow-up query proves the statement cursor was released.
        $followUp = $conn->query('SELECT COUNT(*) AS c FROM items')->first();
        $this->assertNotNull($followUp);
        $count = $followUp['c'];
        $this->assertIsInt($count);
        $this->assertSame(5, $count);
    }

    public function testLazyConnection(): void
    {
        $conn = $this->sqlite();

        $this->assertFalse($conn->isConnected());

        $conn->query('SELECT 1')->first();

        $this->assertTrue($conn->isConnected());
    }
}
