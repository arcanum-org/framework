<?php

declare(strict_types=1);

namespace Arcanum\Test\Glitch;

use Arcanum\Glitch\SafeCall;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SafeCall::class)]
final class SafeCallTest extends TestCase
{
    public function testCallReturnsResultOfFunction(): void
    {
        // Act
        $result = SafeCall::call('strtoupper', 'hello');

        // Assert
        $this->assertSame('HELLO', $result);
    }

    public function testCallCapturesWarningFromFailedFunction(): void
    {
        // Act
        $result = SafeCall::call('file_get_contents', '/nonexistent/path/' . uniqid());

        // Assert
        $this->assertFalse($result);
        $lastError = SafeCall::lastError();
        $this->assertNotNull($lastError);
        $this->assertStringContainsString('file_get_contents', $lastError);
    }

    public function testLastErrorIsNullAfterSuccessfulCall(): void
    {
        // Act
        SafeCall::call('strtoupper', 'hello');

        // Assert
        $this->assertNull(SafeCall::lastError());
    }

    public function testLastErrorResetsOnEachCall(): void
    {
        // Arrange — trigger an error first
        SafeCall::call('file_get_contents', '/nonexistent/path/' . uniqid());
        $this->assertNotNull(SafeCall::lastError());

        // Act — successful call should reset
        SafeCall::call('strtoupper', 'hello');

        // Assert
        $this->assertNull(SafeCall::lastError());
    }

    public function testErrorHandlerIsRestoredAfterCall(): void
    {
        // Arrange
        $originalHandler = set_error_handler(function () {
            return true;
        });
        restore_error_handler();

        // Act
        SafeCall::call('strtoupper', 'hello');

        // Assert — the error handler should be restored to what it was before
        $currentHandler = set_error_handler(function () {
            return true;
        });
        restore_error_handler();
        $this->assertSame($originalHandler, $currentHandler);
    }

    public function testCallWithFileFunction(): void
    {
        // Arrange
        $tempFile = tempnam(sys_get_temp_dir(), 'safecall_test_');
        file_put_contents((string) $tempFile, "line1\nline2");

        try {
            // Act
            $result = SafeCall::call('file', $tempFile, \FILE_IGNORE_NEW_LINES);

            // Assert
            $this->assertSame(['line1', 'line2'], $result);
            $this->assertNull(SafeCall::lastError());
        } finally {
            @unlink((string) $tempFile);
        }
    }

    public function testCallWithFileFunctionOnNonexistentPath(): void
    {
        // Act
        $result = SafeCall::call('file', '/nonexistent/path/' . uniqid());

        // Assert
        $this->assertFalse($result);
        $this->assertNotNull(SafeCall::lastError());
    }
}
