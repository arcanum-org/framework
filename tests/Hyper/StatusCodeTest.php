<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Phrase;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(StatusCode::class)]
final class StatusCodeTest extends TestCase
{
    public function testReason(): void
    {
        // Arrange
        $ok = StatusCode::OK;
        $notFound = StatusCode::NotFound;
        $teapot = StatusCode::ImATeapot;

        // Act
        $okReason = $ok->reason();
        $notFoundReason = $notFound->reason();
        $teapotReason = $teapot->reason();

        // Assert
        $this->assertSame(Phrase::OK, $okReason);
        $this->assertSame(Phrase::NotFound, $notFoundReason);
        $this->assertSame(Phrase::ImATeapot, $teapotReason);
    }

    public function testIsInformational(): void
    {
        // Arrange
        $ok = StatusCode::OK;
        $notFound = StatusCode::NotFound;
        $teapot = StatusCode::ImATeapot;
        $switchingProtocols = StatusCode::SwitchingProtocols;

        // Act
        $okIsInformational = $ok->isInformational();
        $notFoundIsInformational = $notFound->isInformational();
        $teapotIsInformational = $teapot->isInformational();
        $switchingProtocolsIsInformational = $switchingProtocols->isInformational();

        // Assert
        $this->assertFalse($okIsInformational);
        $this->assertFalse($notFoundIsInformational);
        $this->assertFalse($teapotIsInformational);
        $this->assertTrue($switchingProtocolsIsInformational);
    }
}
