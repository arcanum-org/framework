<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Hyper\StatusCode;
use Arcanum\Hyper\Phrase;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Phrase::class)]
#[UsesClass(StatusCode::class)]
final class PhraseTest extends TestCase
{
    public function testCodeReturnsMatchingStatusCodeForAllCases(): void
    {
        foreach (Phrase::cases() as $phrase) {
            $statusCode = $phrase->code();
            $this->assertSame(
                $phrase->value,
                $statusCode->reason()->value,
                "Phrase::{$phrase->name}->code()->reason() should round-trip back to the same phrase"
            );
        }
    }
}
