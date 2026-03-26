<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Continuum;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Flow\Continuum\ContinuationCollection;
use Arcanum\Flow\Continuum\Continuum;
use Arcanum\Flow\Continuum\Continuation;
use Arcanum\Flow\Continuum\Progression;

#[CoversClass(ContinuationCollection::class)]
#[UsesClass(Continuum::class)]
final class ContinuationCollectionTest extends TestCase
{
    public function testContinuationCollection(): void
    {
        // Arrange
        $foo = new \stdClass();
        $bar = new \stdClass();
        $baz = new \stdClass();
        $continuationCollection = new ContinuationCollection();

        /** @var Continuation&\PHPUnit\Framework\MockObject\MockObject */
        $continuationFoo = $this->getMockBuilder(Continuation::class)
            ->getMock();

        $progressionFoo = $this->createStub(Progression::class);

        $continuationFoo->expects($this->once())
            ->method('add')
            ->with($progressionFoo);

        $continuationFoo->expects($this->once())
            ->method('send')
            ->with($foo)
            ->willReturn($foo);

        /** @var Continuation&\PHPUnit\Framework\MockObject\MockObject */
        $continuationBar = $this->getMockBuilder(Continuation::class)
            ->getMock();

        $progressionBar = $this->createStub(Progression::class);

        $continuationBar->expects($this->once())
            ->method('add')
            ->with($progressionBar);

        $continuationBar->expects($this->once())
            ->method('send')
            ->with($bar)
            ->willReturn($bar);

        /** @var Continuation&\PHPUnit\Framework\MockObject\MockObject */
        $continuationBaz = $this->getMockBuilder(Continuation::class)
            ->getMock();

        $progressionBaz = $this->createStub(Progression::class);

        $continuationBaz->expects($this->once())
            ->method('add')
            ->with($progressionBaz);

        $continuationBaz->expects($this->once())
            ->method('send')
            ->with($baz)
            ->willReturn($baz);

        // Act
        $setFoo = $continuationCollection->continuation('foo', $continuationFoo);
        $setBar = $continuationCollection->continuation('bar', $continuationBar);
        $setBaz = $continuationCollection->continuation('baz', $continuationBaz);
        $continuationCollection->add('foo', $progressionFoo);
        $continuationCollection->add('bar', $progressionBar);
        $continuationCollection->add('baz', $progressionBaz);

        $continuationCollection->send('foo', $foo);
        $continuationCollection->send('bar', $bar);
        $continuationCollection->send('baz', $baz);

        // Assert
        $this->assertSame($continuationFoo, $setFoo);
        $this->assertSame($continuationBar, $setBar);
        $this->assertSame($continuationBaz, $setBaz);

        $this->assertSame($continuationFoo, $continuationCollection->continuation('foo'));
        $this->assertSame($continuationBar, $continuationCollection->continuation('bar'));
        $this->assertSame($continuationBaz, $continuationCollection->continuation('baz'));
    }
}
