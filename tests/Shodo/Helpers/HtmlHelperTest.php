<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Helpers;

use Arcanum\Session\ActiveSession;
use Arcanum\Session\CsrfToken;
use Arcanum\Session\Session;
use Arcanum\Session\SessionId;
use Arcanum\Shodo\Helpers\HtmlHelper;
use Arcanum\Toolkit\Random;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(HtmlHelper::class)]
#[UsesClass(ActiveSession::class)]
#[UsesClass(Session::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(CsrfToken::class)]
#[UsesClass(Random::class)]
final class HtmlHelperTest extends TestCase
{
    private function helperWithSession(): HtmlHelper
    {
        $session = new Session(SessionId::generate());
        $active = new ActiveSession();
        $active->set($session);

        return new HtmlHelper($active);
    }

    public function testCsrfReturnsHiddenInput(): void
    {
        // Arrange
        $helper = $this->helperWithSession();

        // Act
        $result = $helper->csrf();

        // Assert
        $this->assertStringStartsWith('<input type="hidden" name="_token" value="', $result);
        $this->assertStringEndsWith('">', $result);
    }

    public function testCsrfTokenReturnsRawString(): void
    {
        // Arrange
        $helper = $this->helperWithSession();

        // Act
        $result = $helper->csrfToken();

        // Assert — 64-character hex string
        $this->assertSame(64, strlen($result));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result);
    }

    public function testCsrfTokenMatchesCsrfHiddenInput(): void
    {
        // Arrange
        $helper = $this->helperWithSession();

        // Act
        $token = $helper->csrfToken();
        $html = $helper->csrf();

        // Assert
        $this->assertStringContainsString($token, $html);
    }

    public function testNonceReturnsBase64String(): void
    {
        // Arrange
        $helper = $this->helperWithSession();

        // Act
        $result = $helper->nonce();

        // Assert — 16 bytes base64-encoded = 24 characters
        $this->assertSame(24, strlen($result));
        $this->assertNotFalse(base64_decode($result, true));
    }

    public function testNonceIsUniquePerCall(): void
    {
        // Arrange
        $helper = $this->helperWithSession();

        // Act
        $first = $helper->nonce();
        $second = $helper->nonce();

        // Assert
        $this->assertNotSame($first, $second);
    }

    public function testClassIfTrueReturnsClass(): void
    {
        // Arrange
        $helper = $this->helperWithSession();

        // Act
        $result = $helper->classIf(true, 'selected');

        // Assert
        $this->assertSame('selected', $result);
    }

    public function testClassIfFalseReturnsEmpty(): void
    {
        // Arrange
        $helper = $this->helperWithSession();

        // Act
        $result = $helper->classIf(false, 'selected');

        // Assert
        $this->assertSame('', $result);
    }
}
