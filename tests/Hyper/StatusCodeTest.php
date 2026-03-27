<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Phrase;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(StatusCode::class)]
#[UsesClass(Phrase::class)]
final class StatusCodeTest extends TestCase
{
    public function testReasonReturnsMatchingPhraseForAllCases(): void
    {
        foreach (StatusCode::cases() as $statusCode) {
            $phrase = $statusCode->reason();
            $this->assertSame(
                $statusCode->value,
                $phrase->code()->value,
                "StatusCode::{$statusCode->name}->reason()->code() should round-trip back to the same status code"
            );
        }
    }

    public function testIsInformational(): void
    {
        $this->assertTrue(StatusCode::Continue->isInformational());
        $this->assertTrue(StatusCode::SwitchingProtocols->isInformational());
        $this->assertTrue(StatusCode::Processing->isInformational());
        $this->assertFalse(StatusCode::OK->isInformational());
        $this->assertFalse(StatusCode::NotFound->isInformational());
        $this->assertFalse(StatusCode::InternalServerError->isInformational());
    }
}
