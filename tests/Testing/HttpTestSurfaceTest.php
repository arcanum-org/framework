<?php

declare(strict_types=1);

namespace Arcanum\Test\Testing;

use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Test\Fixture\Testing\CapturingTestHandler;
use Arcanum\Test\Fixture\Testing\RenderingExceptionRenderer;
use Arcanum\Testing\HttpTestSurface;
use Arcanum\Testing\Internal\TestHyperKernel;
use Arcanum\Testing\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(HttpTestSurface::class)]
#[CoversClass(TestHyperKernel::class)]
final class HttpTestSurfaceTest extends TestCase
{
    public function testKernelHttpReturnsMemoizedSurface(): void
    {
        $kernel = new TestKernel();

        $first = $kernel->http();
        $second = $kernel->http();

        $this->assertInstanceOf(HttpTestSurface::class, $first);
        $this->assertSame($first, $second);
    }

    public function testGetDispatchesToInstalledCoreHandler(): void
    {
        $kernel = new TestKernel();
        $handler = new CapturingTestHandler();
        $surface = $kernel->http()->setCoreHandler($handler);

        $response = $surface->get('/widgets?limit=5');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertNotNull($handler->captured);
        $this->assertSame('GET', $handler->captured->getMethod());
        $this->assertSame('/widgets', $handler->captured->getUri()->getPath());
        $this->assertSame(['limit' => '5'], $handler->captured->getQueryParams());
    }

    public function testPostPutPatchDeleteAllReachHandlerWithBody(): void
    {
        $kernel = new TestKernel();
        $handler = new CapturingTestHandler();
        $surface = $kernel->http()->setCoreHandler($handler);

        $surface->post('/items', '{"name":"a"}');
        $this->assertNotNull($handler->captured);
        $this->assertSame('POST', $handler->captured->getMethod());
        $this->assertSame('{"name":"a"}', (string) $handler->captured->getBody());

        $surface->put('/items/1', '{"name":"b"}');
        $this->assertNotNull($handler->captured);
        $this->assertSame('PUT', $handler->captured->getMethod());

        $surface->patch('/items/1', '{"name":"c"}');
        $this->assertNotNull($handler->captured);
        $this->assertSame('PATCH', $handler->captured->getMethod());

        $surface->delete('/items/1');
        $this->assertNotNull($handler->captured);
        $this->assertSame('DELETE', $handler->captured->getMethod());
    }

    public function testWithHeaderPersistsAcrossRequests(): void
    {
        $kernel = new TestKernel();
        $handler = new CapturingTestHandler();
        $surface = $kernel->http()->setCoreHandler($handler);

        $surface->withHeader('X-Test', 'one')->withHeader('Authorization', 'Bearer abc');

        $surface->get('/a');
        $this->assertNotNull($handler->captured);
        $this->assertSame('one', $handler->captured->getHeaderLine('X-Test'));
        $this->assertSame('Bearer abc', $handler->captured->getHeaderLine('Authorization'));

        $surface->get('/b');
        $this->assertNotNull($handler->captured);
        $this->assertSame('one', $handler->captured->getHeaderLine('X-Test'));
    }

    public function testJsonRequestBodyIsParsedByKernel(): void
    {
        // Verifies the wrapped HyperKernel's prepareRequest() runs — the
        // body is a real stream the kernel reads, json-decodes, and exposes
        // via getParsedBody().
        $kernel = new TestKernel();
        $handler = new CapturingTestHandler();
        $kernel->http()
            ->setCoreHandler($handler)
            ->withHeader('Content-Type', 'application/json');

        $kernel->http()->post('/items', '{"name":"Alice"}');

        $this->assertNotNull($handler->captured);
        $this->assertSame(['name' => 'Alice'], $handler->captured->getParsedBody());
    }

    public function testTerminateDelegatesToWrappedKernel(): void
    {
        // After a successful dispatch, terminate() should not throw and
        // should be safe to call (it dispatches ResponseSent if a listener
        // is registered, which we cover end-to-end in LifecycleEventTest).
        $kernel = new TestKernel();
        $handler = new CapturingTestHandler();
        $surface = $kernel->http()->setCoreHandler($handler);

        $surface->get('/anything');
        $surface->terminate();

        $this->assertNotNull($handler->captured);
    }

    public function testUnroutedRequestRendersThrough404Path(): void
    {
        // No core handler installed → kernel throws HttpException(NotFound)
        // and routes through the registered ExceptionRenderer.
        $kernel = new TestKernel();
        $kernel->container()->instance(ExceptionRenderer::class, new RenderingExceptionRenderer());

        $response = $kernel->http()->get('/nope');

        $this->assertSame(404, $response->getStatusCode());
    }
}
