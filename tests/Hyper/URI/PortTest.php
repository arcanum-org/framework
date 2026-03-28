<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper\URI;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Arcanum\Hyper\URI\Port;

#[CoversClass(Port::class)]
final class PortTest extends TestCase
{
    public function testPort(): void
    {
        // Arrange
        $port = new Port(8080);

        // Act
        $data = (string)$port;

        // Assert
        $this->assertSame('8080', $data);
    }

    public function testPortThrowsInvalidArgumentExceptionIfPortIsNotValid(): void
    {
        // Arrange
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Port must be between 0 and 65535.');

        // Act
        new Port(65536);
    }

    public function testStringPort(): void
    {
        // Arrange
        $port = new Port('8080');

        // Act
        $data = (string)$port;

        // Assert
        $this->assertSame('8080', $data);
    }
}
