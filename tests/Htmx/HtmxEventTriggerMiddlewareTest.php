<?php

declare(strict_types=1);

namespace Arcanum\Test\Htmx;

use Arcanum\Htmx\ClientBroadcast;
use Arcanum\Htmx\EventCapture;
use Arcanum\Htmx\HtmxEventTriggerMiddleware;
use Arcanum\Htmx\HtmxResponse;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(HtmxEventTriggerMiddleware::class)]
#[UsesClass(EventCapture::class)]
#[UsesClass(HtmxResponse::class)]
final class HtmxEventTriggerMiddlewareTest extends TestCase
{
    /**
     * @param array<string, string> $requestHeaders
     * @param array<string, string> $responseHeaders
     * @param list<ClientBroadcast> $events
     */
    private function process(
        array $requestHeaders = [],
        array $responseHeaders = [],
        array $events = [],
    ): ResponseInterface {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('hasHeader')
            ->willReturnCallback(fn(string $n) => isset($requestHeaders[$n]));

        $headers = [];
        $makeStub = function () use (&$headers, &$makeStub, &$responseHeaders): ResponseInterface {
            $stub = $this->createStub(ResponseInterface::class);
            $stub->method('hasHeader')
                ->willReturnCallback(fn(string $n) => isset($responseHeaders[$n]) || isset($headers[$n]));
            $stub->method('getHeaderLine')
                ->willReturnCallback(fn(string $n) => $headers[$n] ?? $responseHeaders[$n] ?? '');
            $stub->method('withHeader')
                ->willReturnCallback(function (string $n, string $v) use (&$headers, $makeStub) {
                    $headers[$n] = $v;
                    return $makeStub();
                });
            return $stub;
        };
        $response = $makeStub();

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);

        // Set up EventCapture with pre-captured events
        $inner = $this->createStub(EventDispatcherInterface::class);
        $inner->method('dispatch')->willReturnArgument(0);
        $capture = new EventCapture($inner);
        foreach ($events as $event) {
            $capture->dispatch($event);
        }

        $middleware = new HtmxEventTriggerMiddleware($capture);

        return $middleware->process($request, $handler);
    }

    public function testNoOpForNonHtmxRequest(): void
    {
        // Arrange & Act
        $event = $this->simpleBroadcast('test');
        $response = $this->process([], [], [$event]);

        // Assert — no HX-Trigger header added
        $this->assertFalse($response->hasHeader('HX-Trigger'));
    }

    public function testNoOpWhenNoEventsWereCaptured(): void
    {
        // Arrange & Act
        $response = $this->process(['HX-Request' => 'true']);

        // Assert
        $this->assertFalse($response->hasHeader('HX-Trigger'));
    }

    public function testSingleImmediateTrigger(): void
    {
        // Arrange & Act
        $response = $this->process(
            ['HX-Request' => 'true'],
            [],
            [$this->simpleBroadcast('cart-updated')],
        );

        // Assert
        $this->assertSame('cart-updated', $response->getHeaderLine('HX-Trigger'));
    }

    public function testMultipleTriggersMerge(): void
    {
        // Arrange & Act
        $response = $this->process(
            ['HX-Request' => 'true'],
            [],
            [
                $this->simpleBroadcast('cart-updated'),
                $this->simpleBroadcast('inventory-changed'),
            ],
        );

        // Assert
        $header = $response->getHeaderLine('HX-Trigger');
        $this->assertStringContainsString('cart-updated', $header);
        $this->assertStringContainsString('inventory-changed', $header);
    }

    public function testCopiesLocationToHxLocation(): void
    {
        // Arrange & Act
        $response = $this->process(
            ['HX-Request' => 'true'],
            ['Location' => '/products/42'],
        );

        // Assert
        $this->assertSame('/products/42', $response->getHeaderLine('HX-Location'));
    }

    public function testDoesNotCopyLocationForNonHtmxRequest(): void
    {
        // Arrange & Act
        $response = $this->process([], ['Location' => '/products/42']);

        // Assert
        $this->assertFalse($response->hasHeader('HX-Location'));
    }

    private function simpleBroadcast(string $name): ClientBroadcast
    {
        return new class ($name) implements ClientBroadcast {
            public function __construct(private readonly string $name)
            {
            }

            public function eventName(): string
            {
                return $this->name;
            }

            public function payload(): array
            {
                return [];
            }
        };
    }
}
