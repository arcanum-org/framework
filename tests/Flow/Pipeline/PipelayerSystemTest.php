<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Pipeline;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Flow\Pipeline\PipelayerSystem;
use Arcanum\Flow\Pipeline\Pipeline;
use Arcanum\Flow\Pipeline\Pipelayer;
use Arcanum\Flow\Stage;

#[CoversClass(PipelayerSystem::class)]
#[UsesClass(Pipeline::class)]
final class PipelayerSystemTest extends TestCase
{
    public function testPipelayerSystem(): void
    {
        // Arrange
        $foo = new \stdClass();
        $bar = new \stdClass();
        $baz = new \stdClass();
        $pipelayerSystem = new PipelayerSystem();

        /** @var Pipelayer&\PHPUnit\Framework\MockObject\MockObject */
        $pipelayerFoo = $this->getMockBuilder(Pipelayer::class)
            ->getMock();

        $stageFoo = $this->createStub(Stage::class);

        $pipelayerFoo->expects($this->once())
            ->method('pipe')
            ->with($stageFoo);

        $pipelayerFoo->expects($this->once())
            ->method('send')
            ->with($foo)
            ->willReturn($foo);

        /** @var Pipelayer&\PHPUnit\Framework\MockObject\MockObject */
        $pipelayerBar = $this->getMockBuilder(Pipelayer::class)
            ->getMock();

        $stageBar = $this->createStub(Stage::class);

        $pipelayerBar->expects($this->once())
            ->method('pipe')
            ->with($stageBar);

        $pipelayerBar->expects($this->once())
            ->method('send')
            ->with($bar)
            ->willReturn($bar);

        /** @var Pipelayer&\PHPUnit\Framework\MockObject\MockObject */
        $pipelayerBaz = $this->getMockBuilder(Pipelayer::class)
            ->getMock();

        $stageBaz = $this->createStub(Stage::class);

        $pipelayerBaz->expects($this->once())
            ->method('pipe')
            ->with($stageBaz);

        $pipelayerBaz->expects($this->once())
            ->method('send')
            ->with($baz)
            ->willReturn($baz);

        // Act
        $setFoo = $pipelayerSystem->pipeline('foo', $pipelayerFoo);
        $setBar = $pipelayerSystem->pipeline('bar', $pipelayerBar);
        $setBaz = $pipelayerSystem->pipeline('baz', $pipelayerBaz);
        $pipelayerSystem->pipe('foo', $stageFoo);
        $pipelayerSystem->pipe('bar', $stageBar);
        $pipelayerSystem->pipe('baz', $stageBaz);

        $pipelayerSystem->send('foo', $foo);
        $pipelayerSystem->send('bar', $bar);
        $pipelayerSystem->send('baz', $baz);

        $pipelayerSystem->purge('foo');

        // Assert
        $this->assertSame($pipelayerFoo, $setFoo);
        $this->assertSame($pipelayerBar, $setBar);
        $this->assertSame($pipelayerBaz, $setBaz);

        $this->assertNotSame($pipelayerFoo, $pipelayerSystem->pipeline('foo'));
        $this->assertSame($pipelayerBar, $pipelayerSystem->pipeline('bar'));
        $this->assertSame($pipelayerBaz, $pipelayerSystem->pipeline('baz'));
    }
}
