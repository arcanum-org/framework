<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor;

use Arcanum\Flow\Conveyor\Command;
use Arcanum\Flow\Conveyor\HandlerProxy;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Command::class)]
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

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment', []);

        $this->assertNull($command->get('nonexistent'));
        $this->assertSame('default', $command->get('nonexistent', 'default'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment', [
            'amount' => 100,
        ]);

        $this->assertTrue($command->has('amount'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment', []);

        $this->assertFalse($command->has('nonexistent'));
    }

    public function testMagicGetAccessesProperties(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment', [
            'amount' => 100,
        ]);

        // @phpstan-ignore property.notFound
        $this->assertSame(100, $command->amount);
    }

    public function testMagicIssetChecksProperties(): void
    {
        $command = new Command('App\\Store\\Command\\MakePayment', [
            'amount' => 100,
        ]);

        $this->assertTrue(isset($command->amount));
        $this->assertFalse(isset($command->nonexistent));
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
