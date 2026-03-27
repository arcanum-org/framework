<?php

declare(strict_types=1);

namespace Arcanum\Test\Glitch;

use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\StatusCode;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HttpException::class)]
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
}
