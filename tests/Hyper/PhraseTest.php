<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Phrase;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Phrase::class)]
final class PhraseTest extends TestCase
{
    public function testCode(): void
    {
        // Arrange
        $ok = Phrase::OK;
        $notFound = Phrase::NotFound;
        $teapot = Phrase::ImATeapot;

        // Act
        $okReason = $ok->code();
        $notFoundReason = $notFound->code();
        $teapotReason = $teapot->code();

        // Assert
        $this->assertSame(StatusCode::OK, $okReason);
        $this->assertSame(StatusCode::NotFound, $notFoundReason);
        $this->assertSame(StatusCode::ImATeapot, $teapotReason);
    }
}
