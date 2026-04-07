<?php

declare(strict_types=1);

namespace Arcanum\Test\Forge;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Arcanum\Forge\WriteResult;

#[CoversClass(WriteResult::class)]
final class WriteResultTest extends TestCase
{
    public function testAffectedRowsIsReturned(): void
    {
        $result = new WriteResult(affectedRows: 3);

        $this->assertSame(3, $result->affectedRows());
    }

    public function testLastInsertIdIsReturned(): void
    {
        $result = new WriteResult(affectedRows: 1, lastInsertId: '42');

        $this->assertSame('42', $result->lastInsertId());
    }

    public function testLastInsertIdDefaultsToEmptyString(): void
    {
        $result = new WriteResult(affectedRows: 5);

        $this->assertSame('', $result->lastInsertId());
    }

    public function testZeroAffectedRowsAllowed(): void
    {
        $result = new WriteResult(affectedRows: 0);

        $this->assertSame(0, $result->affectedRows());
    }
}
