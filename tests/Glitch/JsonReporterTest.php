<?php

declare(strict_types=1);

namespace Arcanum\Test\Glitch;

use Arcanum\Glitch\JsonReporter;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JsonReporter::class)]
final class JsonReporterTest extends TestCase
{
    /**
     * Invoke the reporter and decode the JSON output.
     *
     * @return array{error: array<string, mixed>}
     */
    private function captureJson(JsonReporter $reporter, \Throwable $exception): array
    {
        ob_start();
        $reporter($exception);
        $output = (string) ob_get_clean();

        $data = json_decode($output, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertIsArray($data['error']);

        /** @var array{error: array<string, mixed>} */
        return $data;
    }

    public function testOutputsJsonWithCodeAndMessage(): void
    {
        // Arrange
        $reporter = new JsonReporter(debug: false);
        $exception = new \RuntimeException('Something went wrong', 503);

        // Act
        $data = $this->captureJson($reporter, $exception);

        // Assert
        $this->assertSame(503, $data['error']['code']);
        $this->assertSame('Something went wrong', $data['error']['message']);
        $this->assertArrayNotHasKey('trace', $data['error']);
        $this->assertArrayNotHasKey('exception', $data['error']);
        $this->assertArrayNotHasKey('file', $data['error']);
        $this->assertArrayNotHasKey('line', $data['error']);
    }

    public function testIncludesTraceInDebugMode(): void
    {
        // Arrange
        $reporter = new JsonReporter(debug: true);
        $exception = new \RuntimeException('Debug error', 500);

        // Act
        $data = $this->captureJson($reporter, $exception);

        // Assert
        $this->assertSame(500, $data['error']['code']);
        $this->assertSame('Debug error', $data['error']['message']);
        $this->assertSame(\RuntimeException::class, $data['error']['exception']);
        $this->assertArrayHasKey('file', $data['error']);
        $this->assertArrayHasKey('line', $data['error']);
        $this->assertArrayHasKey('trace', $data['error']);
    }

    public function testDefaultsTo500ForNonHttpStatusCode(): void
    {
        // Arrange
        $reporter = new JsonReporter();
        $exception = new \RuntimeException('Bad code', 42);

        // Act
        $data = $this->captureJson($reporter, $exception);

        // Assert
        $this->assertSame(500, $data['error']['code']);
    }

    public function testDefaultsTo500ForZeroCode(): void
    {
        // Arrange
        $reporter = new JsonReporter();
        $exception = new \RuntimeException('No code');

        // Act
        $data = $this->captureJson($reporter, $exception);

        // Assert
        $this->assertSame(500, $data['error']['code']);
    }

    public function testUsesExceptionCodeWhen4xx(): void
    {
        // Arrange
        $reporter = new JsonReporter();
        $exception = new \RuntimeException('Not found', 404);

        // Act
        $data = $this->captureJson($reporter, $exception);

        // Assert
        $this->assertSame(404, $data['error']['code']);
    }

    public function testHandlesAllExceptions(): void
    {
        // Arrange
        $reporter = new JsonReporter();

        // Assert
        $this->assertTrue($reporter->handles(\RuntimeException::class));
        $this->assertTrue($reporter->handles(\InvalidArgumentException::class));
        $this->assertTrue($reporter->handles(\Throwable::class));
    }

    public function testOutputIsValidJson(): void
    {
        // Arrange
        $reporter = new JsonReporter(debug: true);
        $exception = new \RuntimeException('Path: /foo/bar', 500);

        // Act
        ob_start();
        $reporter($exception);
        $output = (string) ob_get_clean();

        // Assert
        $this->assertNotNull(json_decode($output));
        $this->assertSame(JSON_ERROR_NONE, json_last_error());
    }
}
