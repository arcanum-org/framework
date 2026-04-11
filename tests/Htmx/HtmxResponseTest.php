<?php

declare(strict_types=1);

namespace Arcanum\Test\Htmx;

use Arcanum\Htmx\HtmxLocation;
use Arcanum\Htmx\HtmxResponse;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(HtmxResponse::class)]
#[UsesClass(HtmxLocation::class)]
final class HtmxResponseTest extends TestCase
{
    /**
     * Build a stub ResponseInterface that accumulates headers in a shared
     * array. Each withHeader/withAddedHeader returns a new stub that
     * shares the same backing store — good enough for testing header
     * composition without a real PSR-7 implementation.
     *
     * @param array<string, list<string>> $headers
     */
    private function stubResponse(array &$headers): ResponseInterface
    {
        $stub = $this->createStub(ResponseInterface::class);

        $stub->method('withHeader')
            ->willReturnCallback(function (string $name, string $value) use (&$headers) {
                $headers[$name] = [$value];
                return $this->stubResponse($headers);
            });

        $stub->method('withAddedHeader')
            ->willReturnCallback(function (string $name, string $value) use (&$headers) {
                $headers[$name][] = $value;
                return $this->stubResponse($headers);
            });

        $stub->method('getHeaderLine')
            ->willReturnCallback(function (string $name) use (&$headers) {
                return implode(', ', $headers[$name] ?? []);
            });

        $stub->method('hasHeader')
            ->willReturnCallback(function (string $name) use (&$headers) {
                return isset($headers[$name]);
            });

        return $stub;
    }

    private function emptyResponse(): ResponseInterface
    {
        $headers = [];
        return $this->stubResponse($headers);
    }

    // ------------------------------------------------------------------
    // Simple headers
    // ------------------------------------------------------------------

    public function testWithLocation(): void
    {
        // Arrange
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $response = $builder->withLocation('/dashboard')->toResponse();

        // Assert
        $this->assertSame('/dashboard', $response->getHeaderLine('HX-Location'));
    }

    public function testWithLocationObject(): void
    {
        // Arrange
        $location = new HtmxLocation('/dashboard', target: '#main', swap: 'outerHTML');
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $response = $builder->withLocation($location)->toResponse();

        // Assert
        $header = $response->getHeaderLine('HX-Location');
        $decoded = json_decode($header, true);
        $this->assertIsArray($decoded);
        $this->assertSame('/dashboard', $decoded['path']);
        $this->assertSame('#main', $decoded['target']);
        $this->assertSame('outerHTML', $decoded['swap']);
    }

    public function testWithPushUrl(): void
    {
        // Arrange
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $response = $builder->withPushUrl('/products?page=2')->toResponse();

        // Assert
        $this->assertSame('/products?page=2', $response->getHeaderLine('HX-Push-Url'));
    }

    public function testWithReplaceUrl(): void
    {
        // Arrange
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $response = $builder->withReplaceUrl('/canonical')->toResponse();

        // Assert
        $this->assertSame('/canonical', $response->getHeaderLine('HX-Replace-Url'));
    }

    public function testWithRedirect(): void
    {
        // Arrange
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $response = $builder->withRedirect('/login')->toResponse();

        // Assert
        $this->assertSame('/login', $response->getHeaderLine('HX-Redirect'));
    }

    public function testWithRefresh(): void
    {
        // Arrange
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $response = $builder->withRefresh()->toResponse();

        // Assert
        $this->assertSame('true', $response->getHeaderLine('HX-Refresh'));
    }

    public function testWithRetarget(): void
    {
        // Arrange
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $response = $builder->withRetarget('#error-panel')->toResponse();

        // Assert
        $this->assertSame('#error-panel', $response->getHeaderLine('HX-Retarget'));
    }

    public function testWithReswap(): void
    {
        // Arrange
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $response = $builder->withReswap('outerHTML')->toResponse();

        // Assert
        $this->assertSame('outerHTML', $response->getHeaderLine('HX-Reswap'));
    }

    public function testWithReselect(): void
    {
        // Arrange
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $response = $builder->withReselect('.content')->toResponse();

        // Assert
        $this->assertSame('.content', $response->getHeaderLine('HX-Reselect'));
    }

    // ------------------------------------------------------------------
    // Triggers
    // ------------------------------------------------------------------

    public function testSingleSignalOnlyTrigger(): void
    {
        // Arrange
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $response = $builder->withTrigger('cart-updated')->toResponse();

        // Assert — simple comma-separated form
        $this->assertSame('cart-updated', $response->getHeaderLine('HX-Trigger'));
    }

    public function testMultipleSignalOnlyTriggers(): void
    {
        // Arrange
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $response = $builder
            ->withTrigger('cart-updated')
            ->withTrigger('inventory-changed')
            ->toResponse();

        // Assert — comma-separated
        $this->assertSame(
            'cart-updated, inventory-changed',
            $response->getHeaderLine('HX-Trigger'),
        );
    }

    public function testTriggerWithPayloadUsesJson(): void
    {
        // Arrange
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $response = $builder
            ->withTrigger('cart-updated', ['count' => 5])
            ->toResponse();

        // Assert — JSON object
        $header = $response->getHeaderLine('HX-Trigger');
        $decoded = json_decode($header, true);
        $this->assertIsArray($decoded);
        $this->assertSame(['count' => 5], $decoded['cart-updated']);
    }

    public function testMixedTriggersUseJson(): void
    {
        // Arrange — one with payload, one without
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $response = $builder
            ->withTrigger('simple-signal')
            ->withTrigger('cart-updated', ['count' => 3])
            ->toResponse();

        // Assert — JSON form because at least one has a payload
        $header = $response->getHeaderLine('HX-Trigger');
        $decoded = json_decode($header, true);
        $this->assertIsArray($decoded);
        $this->assertSame('simple-signal', $decoded['simple-signal']);
        $this->assertSame(['count' => 3], $decoded['cart-updated']);
    }

    // ------------------------------------------------------------------
    // Immutability
    // ------------------------------------------------------------------

    public function testBuildersAreImmutable(): void
    {
        // Arrange
        $builder = new HtmxResponse($this->emptyResponse());

        // Act
        $withRedirect = $builder->withRedirect('/foo');
        $withRefresh = $builder->withRefresh();

        // Assert — original is unmodified, each branch is independent
        $original = $builder->toResponse();
        $this->assertFalse($original->hasHeader('HX-Redirect'));
        $this->assertFalse($original->hasHeader('HX-Refresh'));

        $redirectResponse = $withRedirect->toResponse();
        $this->assertTrue($redirectResponse->hasHeader('HX-Redirect'));
        $this->assertFalse($redirectResponse->hasHeader('HX-Refresh'));
    }

    // ------------------------------------------------------------------
    // No-op
    // ------------------------------------------------------------------

    public function testEmptyBuilderAddsNoHeaders(): void
    {
        // Arrange
        $inner = $this->emptyResponse();
        $builder = new HtmxResponse($inner);

        // Act
        $response = $builder->toResponse();

        // Assert — no htmx headers added
        $this->assertFalse($response->hasHeader('HX-Trigger'));
        $this->assertFalse($response->hasHeader('HX-Location'));
    }
}
