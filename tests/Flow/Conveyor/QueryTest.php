<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor;

use Arcanum\Flow\Conveyor\DynamicDTO;
use Arcanum\Flow\Conveyor\HandlerProxy;
use Arcanum\Flow\Conveyor\Query;
use Arcanum\Gather\Registry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Query::class)]
#[UsesClass(DynamicDTO::class)]
#[UsesClass(Registry::class)]
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

    public function testHasChecksExistence(): void
    {
        $query = new Query('App\\Catalog\\Query\\Products', [
            'page' => '1',
        ]);

        $this->assertTrue($query->has('page'));
        $this->assertFalse($query->has('nonexistent'));
    }

    public function testAsIntCoerces(): void
    {
        $query = new Query('App\\Catalog\\Query\\Products', [
            'page' => '3',
            'limit' => '25',
        ]);

        $this->assertSame(3, $query->asInt('page'));
        $this->assertSame(25, $query->asInt('limit'));
        $this->assertSame(10, $query->asInt('missing', 10));
    }

    public function testAsStringCoerces(): void
    {
        $query = new Query('App\\Catalog\\Query\\Products', [
            'category' => 'electronics',
        ]);

        $this->assertSame('electronics', $query->asString('category'));
    }

    public function testAsBoolCoerces(): void
    {
        $query = new Query('App\\Catalog\\Query\\Products', [
            'include_archived' => true,
        ]);

        $this->assertTrue($query->asBool('include_archived'));
        $this->assertFalse($query->asBool('missing'));
    }

    public function testToArrayReturnsAllData(): void
    {
        $data = ['page' => '1', 'limit' => '20'];
        $query = new Query('App\\Catalog\\Query\\Products', $data);

        $this->assertSame($data, $query->toArray());
    }
}
