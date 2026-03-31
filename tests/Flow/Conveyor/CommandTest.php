<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor;

use Arcanum\Flow\Conveyor\Command;
use Arcanum\Flow\Conveyor\HandlerProxy;
use Arcanum\Gather\Registry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Command::class)]
#[UsesClass(Registry::class)]

final class CommandTest extends TestCase
{
    public function testImplementsHandlerProxy(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment');

        $this->assertInstanceOf(HandlerProxy::class, $command);
    }

    public function testHandlerBaseNameReturnsVirtualClassName(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment');

        $this->assertSame('App\\Store\\Command\\MakePayment', $command->handlerBaseName());
    }

    public function testGetReturnsValue(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment', [
            'amount' => 99.99,
            'currency' => 'USD',
        ]);

        $this->assertSame(99.99, $command->get('amount'));
        $this->assertSame('USD', $command->get('currency'));
    }

    public function testHasChecksExistence(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment', [
            'amount' => 100,
        ]);

        $this->assertTrue($command->has('amount'));
        $this->assertFalse($command->has('nonexistent'));
    }

    public function testAsStringCoerces(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment', [
            'amount' => 100,
        ]);

        $this->assertSame('100', $command->asString('amount'));
        $this->assertSame('default', $command->asString('missing', 'default'));
    }

    public function testAsIntCoerces(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment', [
            'quantity' => '5',
        ]);

        $this->assertSame(5, $command->asInt('quantity'));
        $this->assertSame(1, $command->asInt('missing', 1));
    }

    public function testAsFloatCoerces(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment', [
            'amount' => '99.99',
        ]);

        $this->assertSame(99.99, $command->asFloat('amount'));
    }

    public function testAsBoolCoerces(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment', [
            'express' => true,
        ]);

        $this->assertTrue($command->asBool('express'));
        $this->assertFalse($command->asBool('missing'));
    }

    public function testToArrayReturnsAllData(): void
    {
        $data = ['amount' => 100, 'currency' => 'EUR'];
        $command = new Command('App\\Store\\Command\\MakePayment', $data);

        $this->assertSame($data, $command->toArray());
    }

    public function testEmptyDataByDefault(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment');

        $this->assertSame([], $command->toArray());
    }
}
