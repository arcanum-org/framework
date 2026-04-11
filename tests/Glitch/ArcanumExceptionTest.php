<?php

declare(strict_types=1);

namespace Arcanum\Test\Glitch;

use Arcanum\Glitch\ArcanumException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ArcanumException::class)]
final class ArcanumExceptionTest extends TestCase
{
    public function testInterfaceRequiresGetTitle(): void
    {
        // Arrange & Act
        $exception = new class ('Something broke') extends \RuntimeException implements ArcanumException {
            public function getTitle(): string
            {
                return 'Test Error';
            }

            public function getSuggestion(): ?string
            {
                return null;
            }
        };

        // Assert
        $this->assertInstanceOf(ArcanumException::class, $exception);
        $this->assertSame('Test Error', $exception->getTitle());
    }

    public function testSuggestionCanBeNull(): void
    {
        // Arrange & Act
        $exception = new class ('Oops') extends \RuntimeException implements ArcanumException {
            public function getTitle(): string
            {
                return 'Null Suggestion';
            }

            public function getSuggestion(): ?string
            {
                return null;
            }
        };

        // Assert
        $this->assertNull($exception->getSuggestion());
    }
}
