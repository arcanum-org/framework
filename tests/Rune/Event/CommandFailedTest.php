<?php

declare(strict_types=1);

namespace Arcanum\Test\Rune\Event;

use Arcanum\Rune\Event\CommandFailed;
use Arcanum\Rune\Input;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CommandFailed::class)]
#[UsesClass(Input::class)]
final class CommandFailedTest extends TestCase
{
    public function testGetInputReturnsInput(): void
    {
        $input = new Input('migrate');
        $exception = new \RuntimeException('Database error');

        $event = new CommandFailed($input, $exception);

        $this->assertSame($input, $event->getInput());
    }

    public function testGetExceptionReturnsException(): void
    {
        $exception = new \RuntimeException('Database error');

        $event = new CommandFailed(new Input('migrate'), $exception);

        $this->assertSame($exception, $event->getException());
    }

    public function testPropagationCanBeStopped(): void
    {
        $event = new CommandFailed(new Input('migrate'), new \RuntimeException());

        $this->assertFalse($event->isPropagationStopped());

        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }
}
