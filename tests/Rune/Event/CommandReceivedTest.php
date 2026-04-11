<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Event;

use Arcanum\Rune\Event\CommandReceived;
use Arcanum\Rune\Input;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CommandReceived::class)]
#[UsesClass(Input::class)]
final class CommandReceivedTest extends TestCase
{
    public function testGetInputReturnsInput(): void
    {
        $input = new Input('migrate');

        $event = new CommandReceived($input);

        $this->assertSame($input, $event->getInput());
    }

    public function testPropagationCanBeStopped(): void
    {
        $event = new CommandReceived(new Input('migrate'));

        $this->assertFalse($event->isPropagationStopped());

        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }
}
