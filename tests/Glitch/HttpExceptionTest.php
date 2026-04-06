<?php

declare(strict_types=1);

namespace Arcanum\Test\Glitch;

use Arcanum\Glitch\ArcanumException;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(HttpException::class)]
#[UsesClass(ArcanumException::class)]
final class HttpExceptionTest extends TestCase
{
    public function testGetStatusCodeReturnsStatusCode(): void
    {
        // Arrange & Act
        $exception = new HttpException(StatusCode::NotFound);

        // Assert
        $this->assertSame(StatusCode::NotFound, $exception->getStatusCode());
    }

    public function testCodeMatchesStatusCodeValue(): void
    {
        // Arrange & Act
        $exception = new HttpException(StatusCode::Forbidden);

        // Assert
        $this->assertSame(403, $exception->getCode());
    }

    public function testCustomMessageIsUsed(): void
    {
        // Arrange & Act
        $exception = new HttpException(StatusCode::NotFound, 'Order not found');

        // Assert
        $this->assertSame('Order not found', $exception->getMessage());
    }

    public function testDefaultMessageIsReasonPhrase(): void
    {
        // Arrange & Act
        $exception = new HttpException(StatusCode::NotFound);

        // Assert
        $this->assertSame('Not Found', $exception->getMessage());
    }

    public function testPreviousExceptionIsPreserved(): void
    {
        // Arrange
        $previous = new \RuntimeException('root cause');

        // Act
        $exception = new HttpException(StatusCode::InternalServerError, 'Something broke', $previous);

        // Assert
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testWorksWithVariousStatusCodes(): void
    {
        // Arrange & Act & Assert
        $this->assertSame(400, (new HttpException(StatusCode::BadRequest))->getCode());
        $this->assertSame(401, (new HttpException(StatusCode::Unauthorized))->getCode());
        $this->assertSame(500, (new HttpException(StatusCode::InternalServerError))->getCode());
        $this->assertSame(503, (new HttpException(StatusCode::ServiceUnavailable))->getCode());
    }

    public function testImplementsArcanumException(): void
    {
        // Arrange & Act
        $exception = new HttpException(StatusCode::NotFound);

        // Assert
        $this->assertInstanceOf(ArcanumException::class, $exception);
    }

    public function testGetTitleReturnReasonPhrase(): void
    {
        // Arrange & Act
        $exception = new HttpException(StatusCode::NotFound);

        // Assert
        $this->assertSame('Not Found', $exception->getTitle());
    }

    public function testGetTitleIsAlwaysReasonPhraseEvenWithCustomMessage(): void
    {
        // Arrange & Act
        $exception = new HttpException(StatusCode::NotFound, 'Order #42 not found');

        // Assert
        $this->assertSame('Not Found', $exception->getTitle());
        $this->assertSame('Order #42 not found', $exception->getMessage());
    }

    public function testGetSuggestionIsNullByDefault(): void
    {
        // Arrange & Act
        $exception = new HttpException(StatusCode::NotFound);

        // Assert
        $this->assertNull($exception->getSuggestion());
    }

    public function testWithSuggestionSetsSuggestionFluently(): void
    {
        // Arrange
        $exception = new HttpException(StatusCode::NotFound, 'Order not found');

        // Act
        $returned = $exception->withSuggestion('Check the order ID and try again');

        // Assert
        $this->assertSame('Check the order ID and try again', $exception->getSuggestion());
        $this->assertSame($exception, $returned);
    }

    public function testWithSuggestionPreservesStatusCodeAndMessage(): void
    {
        // Arrange & Act
        $exception = (new HttpException(StatusCode::Forbidden, 'Access denied'))
            ->withSuggestion('Check your permissions');

        // Assert
        $this->assertSame(StatusCode::Forbidden, $exception->getStatusCode());
        $this->assertSame('Access denied', $exception->getMessage());
        $this->assertSame(403, $exception->getCode());
        $this->assertSame('Check your permissions', $exception->getSuggestion());
    }
}
