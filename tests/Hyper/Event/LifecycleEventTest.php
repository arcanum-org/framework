<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper\Event;

use Arcanum\Echo\Dispatcher;
use Arcanum\Echo\Provider;
use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\Event\RequestFailed;
use Arcanum\Hyper\Event\RequestHandled;
use Arcanum\Hyper\Event\RequestReceived;
use Arcanum\Hyper\Event\ResponseSent;
use Arcanum\Ignition\HyperKernel;
use Arcanum\Test\Fixture\Testing\CapturingTestHandler;
use Arcanum\Test\Fixture\Testing\RenderingExceptionRenderer;
use Arcanum\Testing\TestKernel;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(HyperKernel::class)]
#[CoversClass(RequestReceived::class)]
#[CoversClass(RequestHandled::class)]
#[CoversClass(RequestFailed::class)]
#[CoversClass(ResponseSent::class)]
#[UsesClass(Dispatcher::class)]
#[UsesClass(Provider::class)]
#[UsesClass(\Arcanum\Echo\Event::class)]
#[UsesClass(\Arcanum\Echo\UnknownEvent::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\Pipeline::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\StandardProcessor::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(\Arcanum\Glitch\ArcanumException::class)]
#[UsesClass(\Arcanum\Ignition\Lifecycle::class)]
final class LifecycleEventTest extends TestCase
{
    private function buildKernel(Provider $provider): TestKernel
    {
        $kernel = new TestKernel();
        $kernel->container()->instance(
            EventDispatcherInterface::class,
            new Dispatcher($provider),
        );

        return $kernel;
    }

    // -----------------------------------------------------------
    // RequestReceived
    // -----------------------------------------------------------

    public function testRequestReceivedFiresBeforeHandling(): void
    {
        $provider = new Provider();
        $fired = false;
        $provider->listen(RequestReceived::class, function (RequestReceived $event) use (&$fired) {
            $fired = true;
            return $event;
        });

        $kernel = $this->buildKernel($provider);
        $kernel->http()->setCoreHandler(new CapturingTestHandler());

        $kernel->http()->get('/anything');

        $this->assertTrue($fired);
    }

    public function testRequestReceivedMutationPropagates(): void
    {
        // Listener replaces the request entirely — the capturing core
        // handler should observe the replacement, not the original.
        $provider = new Provider();
        $replacementRequest = $this->createStub(ServerRequestInterface::class);
        $provider->listen(
            RequestReceived::class,
            function (RequestReceived $event) use ($replacementRequest) {
                $event->setRequest($replacementRequest);
                return $event;
            },
        );

        $kernel = $this->buildKernel($provider);
        $handler = new CapturingTestHandler();
        $kernel->http()->setCoreHandler($handler);

        $kernel->http()->get('/anything');

        $this->assertSame($replacementRequest, $handler->captured);
    }

    // -----------------------------------------------------------
    // RequestHandled
    // -----------------------------------------------------------

    public function testRequestHandledFiresAfterResponse(): void
    {
        $provider = new Provider();
        $capturedStatus = null;
        $provider->listen(
            RequestHandled::class,
            function (RequestHandled $event) use (&$capturedStatus) {
                $capturedStatus = $event->getResponse()->getStatusCode();
                return $event;
            },
        );

        $kernel = $this->buildKernel($provider);
        $kernel->http()->setCoreHandler(new CapturingTestHandler());

        $kernel->http()->get('/anything');

        $this->assertSame(200, $capturedStatus);
    }

    // -----------------------------------------------------------
    // RequestFailed
    // -----------------------------------------------------------

    public function testRequestFailedFiresOnException(): void
    {
        // No core handler installed → kernel throws HttpException(NotFound),
        // RequestFailed fires, then the registered ExceptionRenderer renders
        // the response. Mirrors the production failure path.
        $provider = new Provider();
        $capturedException = null;
        $provider->listen(
            RequestFailed::class,
            function (RequestFailed $event) use (&$capturedException) {
                $capturedException = $event->getException();
                return $event;
            },
        );

        $kernel = $this->buildKernel($provider);
        $kernel->container()->instance(ExceptionRenderer::class, new RenderingExceptionRenderer());

        $kernel->http()->get('/anything');

        $this->assertInstanceOf(HttpException::class, $capturedException);
    }

    // -----------------------------------------------------------
    // ResponseSent
    // -----------------------------------------------------------

    public function testResponseSentFiresOnTerminate(): void
    {
        $provider = new Provider();
        $fired = false;
        $provider->listen(ResponseSent::class, function (ResponseSent $event) use (&$fired) {
            $fired = true;
            return $event;
        });

        $kernel = $this->buildKernel($provider);
        $kernel->http()->setCoreHandler(new CapturingTestHandler());

        $kernel->http()->get('/anything');
        $kernel->http()->terminate();

        $this->assertTrue($fired);
    }

    public function testResponseSentCarriesRequestAndResponse(): void
    {
        $provider = new Provider();
        $capturedRequest = null;
        $capturedResponse = null;
        $provider->listen(
            ResponseSent::class,
            function (ResponseSent $event) use (&$capturedRequest, &$capturedResponse) {
                $capturedRequest = $event->getRequest();
                $capturedResponse = $event->getResponse();
                return $event;
            },
        );

        $kernel = $this->buildKernel($provider);
        $kernel->http()->setCoreHandler(new CapturingTestHandler());

        $kernel->http()->get('/anything');
        $kernel->http()->terminate();

        $this->assertNotNull($capturedRequest);
        $this->assertNotNull($capturedResponse);
        $this->assertSame(200, $capturedResponse->getStatusCode());
    }

    // -----------------------------------------------------------
    // No dispatcher registered — no errors
    // -----------------------------------------------------------

    public function testHandleWorksWithoutEventDispatcher(): void
    {
        // TestKernel doesn't bind EventDispatcherInterface by default,
        // so this verifies the kernel's "no dispatcher → no errors" path.
        $kernel = new TestKernel();
        $kernel->http()->setCoreHandler(new CapturingTestHandler());

        $response = $kernel->http()->get('/anything');

        $this->assertSame(200, $response->getStatusCode());
    }
}
