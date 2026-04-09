<?php

declare(strict_types=1);

namespace Arcanum\Test\Htmx;

use Arcanum\Htmx\HtmxRequest;
use Arcanum\Htmx\HtmxRequestType;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(HtmxRequest::class)]
#[CoversClass(HtmxRequestType::class)]
final class HtmxRequestTest extends TestCase
{
    /**
     * Build an HtmxRequest from a map of header names to values.
     *
     * @param array<string, string> $headers
     */
    private function request(array $headers = []): HtmxRequest
    {
        $inner = $this->createStub(ServerRequestInterface::class);

        $inner->method('hasHeader')
            ->willReturnCallback(fn(string $name) => isset($headers[$name]));

        $inner->method('getHeaderLine')
            ->willReturnCallback(fn(string $name) => $headers[$name] ?? '');

        return new HtmxRequest($inner);
    }

    // ------------------------------------------------------------------
    // isHtmx
    // ------------------------------------------------------------------

    public function testIsHtmxReturnsTrueWhenHeaderPresent(): void
    {
        // Arrange
        $req = $this->request(['HX-Request' => 'true']);

        // Act & Assert
        $this->assertTrue($req->isHtmx());
    }

    public function testIsHtmxReturnsFalseForNormalRequest(): void
    {
        // Arrange
        $req = $this->request();

        // Act & Assert
        $this->assertFalse($req->isHtmx());
    }

    // ------------------------------------------------------------------
    // isBoosted
    // ------------------------------------------------------------------

    public function testIsBoostedReturnsTrueWhenHeaderIsTrue(): void
    {
        // Arrange
        $req = $this->request(['HX-Request' => 'true', 'HX-Boosted' => 'true']);

        // Act & Assert
        $this->assertTrue($req->isBoosted());
    }

    public function testIsBoostedReturnsFalseWhenHeaderAbsent(): void
    {
        // Arrange
        $req = $this->request(['HX-Request' => 'true']);

        // Act & Assert
        $this->assertFalse($req->isBoosted());
    }

    // ------------------------------------------------------------------
    // isHistoryRestore
    // ------------------------------------------------------------------

    public function testIsHistoryRestoreReturnsTrueWhenHeaderIsTrue(): void
    {
        // Arrange
        $req = $this->request([
            'HX-Request' => 'true',
            'HX-History-Restore-Request' => 'true',
        ]);

        // Act & Assert
        $this->assertTrue($req->isHistoryRestore());
    }

    public function testIsHistoryRestoreReturnsFalseWhenHeaderAbsent(): void
    {
        // Arrange
        $req = $this->request(['HX-Request' => 'true']);

        // Act & Assert
        $this->assertFalse($req->isHistoryRestore());
    }

    // ------------------------------------------------------------------
    // type
    // ------------------------------------------------------------------

    public function testTypeReturnsNullForNonHtmxRequest(): void
    {
        // Arrange
        $req = $this->request();

        // Act & Assert
        $this->assertNull($req->type());
    }

    public function testTypeReturnsPartialFromHeader(): void
    {
        // Arrange
        $req = $this->request([
            'HX-Request' => 'true',
            'HX-Request-Type' => 'partial',
        ]);

        // Act & Assert
        $this->assertSame(HtmxRequestType::Partial, $req->type());
    }

    public function testTypeReturnsFullFromHeader(): void
    {
        // Arrange
        $req = $this->request([
            'HX-Request' => 'true',
            'HX-Request-Type' => 'full',
        ]);

        // Act & Assert
        $this->assertSame(HtmxRequestType::Full, $req->type());
    }

    public function testTypeFallsBackToPartialForV2Request(): void
    {
        // Arrange — no HX-Request-Type header (htmx v2 compat)
        $req = $this->request(['HX-Request' => 'true']);

        // Act & Assert
        $this->assertSame(HtmxRequestType::Partial, $req->type());
    }

    public function testTypeFallsBackToFullForBoostedV2Request(): void
    {
        // Arrange — boosted request without HX-Request-Type
        $req = $this->request([
            'HX-Request' => 'true',
            'HX-Boosted' => 'true',
        ]);

        // Act & Assert
        $this->assertSame(HtmxRequestType::Full, $req->type());
    }

    // ------------------------------------------------------------------
    // target
    // ------------------------------------------------------------------

    public function testTargetReturnsIdFromHeader(): void
    {
        // Arrange
        $req = $this->request([
            'HX-Request' => 'true',
            'HX-Target' => 'product-list',
        ]);

        // Act & Assert
        $this->assertSame('product-list', $req->target());
    }

    public function testTargetReturnsNullWhenAbsent(): void
    {
        // Arrange — no HX-Target (target element has no id)
        $req = $this->request(['HX-Request' => 'true']);

        // Act & Assert
        $this->assertNull($req->target());
    }

    // ------------------------------------------------------------------
    // swapMode
    // ------------------------------------------------------------------

    public function testSwapModeReturnsValueFromHeader(): void
    {
        // Arrange
        $req = $this->request([
            'HX-Request' => 'true',
            'HX-Swap' => 'outerHTML',
        ]);

        // Act & Assert
        $this->assertSame('outerHTML', $req->swapMode());
    }

    public function testSwapModeReturnsNullWhenAbsent(): void
    {
        // Arrange
        $req = $this->request(['HX-Request' => 'true']);

        // Act & Assert
        $this->assertNull($req->swapMode());
    }

    // ------------------------------------------------------------------
    // triggerId, triggerName, currentUrl, prompt
    // ------------------------------------------------------------------

    public function testTriggerIdReturnsValueFromHeader(): void
    {
        // Arrange
        $req = $this->request([
            'HX-Request' => 'true',
            'HX-Trigger' => 'refresh-btn',
        ]);

        // Act & Assert
        $this->assertSame('refresh-btn', $req->triggerId());
    }

    public function testTriggerNameReturnsValueFromHeader(): void
    {
        // Arrange
        $req = $this->request([
            'HX-Request' => 'true',
            'HX-Trigger-Name' => 'search',
        ]);

        // Act & Assert
        $this->assertSame('search', $req->triggerName());
    }

    public function testCurrentUrlReturnsValueFromHeader(): void
    {
        // Arrange
        $req = $this->request([
            'HX-Request' => 'true',
            'HX-Current-URL' => 'https://example.com/products',
        ]);

        // Act & Assert
        $this->assertSame('https://example.com/products', $req->currentUrl());
    }

    public function testPromptReturnsValueFromHeader(): void
    {
        // Arrange
        $req = $this->request([
            'HX-Request' => 'true',
            'HX-Prompt' => 'Are you sure?',
        ]);

        // Act & Assert
        $this->assertSame('Are you sure?', $req->prompt());
    }

    public function testAllAccessorsReturnNullWhenHeadersAbsent(): void
    {
        // Arrange — bare htmx request with no optional headers
        $req = $this->request(['HX-Request' => 'true']);

        // Act & Assert
        $this->assertNull($req->target());
        $this->assertNull($req->swapMode());
        $this->assertNull($req->triggerId());
        $this->assertNull($req->triggerName());
        $this->assertNull($req->currentUrl());
        $this->assertNull($req->prompt());
    }

    // ------------------------------------------------------------------
    // request()
    // ------------------------------------------------------------------

    public function testRequestReturnsUnderlyingPsr7Request(): void
    {
        // Arrange
        $inner = $this->createStub(ServerRequestInterface::class);
        $req = new HtmxRequest($inner);

        // Act & Assert
        $this->assertSame($inner, $req->request());
    }
}
