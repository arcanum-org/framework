<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Event;

use Arcanum\Rune\Event\CommandCompleted;
use Arcanum\Rune\Input;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CommandCompleted::class)]
#[UsesClass(Input::class)]
final class CommandCompletedTest extends TestCase
{
    public function testGetInputReturnsInput(): void
    {
        $input = new Input('migrate');

        $event = new CommandCompleted($input, 0);

        $this->assertSame($input, $event->getInput());
    }

    public function testGetExitCodeReturnsExitCode(): void
    {
        $event = new CommandCompleted(new Input('migrate'), 1);

        $this->assertSame(1, $event->getExitCode());
    }

    public function testPropagationCanBeStopped(): void
    {
        $event = new CommandCompleted(new Input('migrate'), 0);

        $this->assertFalse($event->isPropagationStopped());

        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }
}
