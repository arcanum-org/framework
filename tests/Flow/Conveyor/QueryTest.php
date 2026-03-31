<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor;

use Arcanum\Flow\Conveyor\HandlerProxy;
use Arcanum\Flow\Conveyor\Query;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Query::class)]
final class QueryTest extends TestCase
{
    public function testImplementsHandlerProxy(): void
    {
        $query = new Query('App\\Catalog\\Query\\Products');

        $this->assertInstanceOf(HandlerProxy::class, $query);
    }

    public function testHandlerBaseNameReturnsVirtualClassName(): void
    {
        $query = new Query('App\\Catalog\\Query\\Products');

        $this->assertSame('App\\Catalog\\Query\\Products', $query->handlerBaseName());
    }

    public function testGetReturnsValue(): void
    {
        $query = new Query('App\\Catalog\\Query\\Products', [
            'page' => '2',
            'category' => 'electronics',
        ]);

        $this->assertSame('2', $query->get('page'));
        $this->assertSame('electronics', $query->get('category'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $query = new Query('App\\Catalog\\Query\\Products', []);

        $this->assertNull($query->get('nonexistent'));
        $this->assertSame(10, $query->get('limit', 10));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $query = new Query('App\\Catalog\\Query\\Products', [
            'page' => '1',
        ]);

        $this->assertTrue($query->has('page'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $query = new Query('App\\Catalog\\Query\\Products', []);

        $this->assertFalse($query->has('nonexistent'));
    }

    public function testMagicGetAccessesProperties(): void
    {
        $query = new Query('App\\Catalog\\Query\\Products', [
            'page' => '2',
        ]);

        // @phpstan-ignore property.notFound
        $this->assertSame('2', $query->page);
    }

    public function testMagicIssetChecksProperties(): void
    {
        $query = new Query('App\\Catalog\\Query\\Products', [
            'page' => '1',
        ]);

        $this->assertTrue(isset($query->page));
        $this->assertFalse(isset($query->nonexistent));
    }

    public function testToArrayReturnsAllData(): void
    {
        $data = ['page' => '1', 'limit' => '20'];
        $query = new Query('App\\Catalog\\Query\\Products', $data);

        $this->assertSame($data, $query->toArray());
    }
}
