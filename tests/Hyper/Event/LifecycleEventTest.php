<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper\Event;

use Arcanum\Cabinet\Application;
use Arcanum\Cabinet\Container;
use Arcanum\Echo\Dispatcher;
use Arcanum\Echo\Provider;
use Arcanum\Glitch\ExceptionHandler;
use Arcanum\Glitch\ExceptionRenderer;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\Event\RequestFailed;
use Arcanum\Hyper\Event\RequestHandled;
use Arcanum\Hyper\Event\RequestReceived;
use Arcanum\Hyper\Event\ResponseSent;
use Arcanum\Hyper\StatusCode;
use Arcanum\Ignition\Bootstrapper;
use Arcanum\Ignition\HyperKernel;
use Arcanum\Test\Fixture\CapturingKernel;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
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
#[UsesClass(CapturingKernel::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(\Arcanum\Glitch\ArcanumException::class)]
final class LifecycleEventTest extends TestCase
{
    private function buildContainer(Provider $provider): Application
    {
        $dispatcher = new Dispatcher($provider);

        $container = $this->createStub(Application::class);
        $container->method('has')->willReturnCallback(
            fn(string $id) => $id === EventDispatcherInterface::class,
        );
        $container->method('get')->willReturnCallback(
            fn(string $id) => match ($id) {
                EventDispatcherInterface::class => $dispatcher,
                default => $this->createStub(Bootstrapper::class),
            },
        );

        return $container;
    }

    // -----------------------------------------------------------
    // RequestReceived
    // -----------------------------------------------------------

    public function testRequestReceivedFiresBeforeHandling(): void
    {
        // Arrange
        $provider = new Provider();
        $fired = false;
        $provider->listen(RequestReceived::class, function (RequestReceived $event) use (&$fired) {
            $fired = true;
            return $event;
        });

        $container = $this->buildContainer($provider);
        $kernel = new CapturingKernel('/app');
        $kernel->bootstrap($container);

        // Act
        $kernel->handle($this->createStub(ServerRequestInterface::class));

        // Assert
        $this->assertTrue($fired);
    }

    public function testRequestReceivedMutationPropagates(): void
    {
        // Arrange — listener replaces the request entirely
        $provider = new Provider();
        $replacementRequest = $this->createStub(ServerRequestInterface::class);
        $provider->listen(RequestReceived::class, function (RequestReceived $event) use ($replacementRequest) {
            $event->setRequest($replacementRequest);
            return $event;
        });

        $container = $this->buildContainer($provider);
        $kernel = new CapturingKernel('/app');
        $kernel->bootstrap($container);

        // Act
        $kernel->handle($this->createStub(ServerRequestInterface::class));

        // Assert — the capturing kernel received the replacement request
        $this->assertSame($replacementRequest, $kernel->capturedRequest);
    }

    // -----------------------------------------------------------
    // RequestHandled
    // -----------------------------------------------------------

    public function testRequestHandledFiresAfterResponse(): void
    {
        // Arrange
        $provider = new Provider();
        $capturedStatus = null;
        $provider->listen(RequestHandled::class, function (RequestHandled $event) use (&$capturedStatus) {
            $capturedStatus = $event->getResponse()->getStatusCode();
            return $event;
        });

        $container = $this->buildContainer($provider);
        $kernel = new CapturingKernel('/app');
        $kernel->bootstrap($container);

        // Act
        $kernel->handle($this->createStub(ServerRequestInterface::class));

        // Assert
        $this->assertSame(200, $capturedStatus);
    }

    // -----------------------------------------------------------
    // RequestFailed
    // -----------------------------------------------------------

    public function testRequestFailedFiresOnException(): void
    {
        // Arrange
        $provider = new Provider();
        $capturedException = null;
        $provider->listen(RequestFailed::class, function (RequestFailed $event) use (&$capturedException) {
            $capturedException = $event->getException();
            return $event;
        });

        $renderer = $this->createStub(ExceptionRenderer::class);
        $renderer->method('render')->willReturn(
            $this->createStub(ResponseInterface::class),
        );

        $container = $this->createStub(Application::class);
        $container->method('has')->willReturnCallback(
            fn(string $id) => match ($id) {
                EventDispatcherInterface::class, ExceptionRenderer::class => true,
                default => false,
            },
        );
        $container->method('get')->willReturnCallback(
            fn(string $id) => match ($id) {
                EventDispatcherInterface::class => new Dispatcher($provider),
                ExceptionRenderer::class => $renderer,
                default => $this->createStub(Bootstrapper::class),
            },
        );

        // Use default HyperKernel — handleRequest() throws NotFound
        $kernel = new HyperKernel('/app');
        $kernel->bootstrap($container);

        // Act
        $kernel->handle($this->createStub(ServerRequestInterface::class));

        // Assert
        $this->assertInstanceOf(HttpException::class, $capturedException);
    }

    // -----------------------------------------------------------
    // ResponseSent
    // -----------------------------------------------------------

    public function testResponseSentFiresOnTerminate(): void
    {
        // Arrange
        $provider = new Provider();
        $fired = false;
        $provider->listen(ResponseSent::class, function (ResponseSent $event) use (&$fired) {
            $fired = true;
            return $event;
        });

        $container = $this->buildContainer($provider);
        $kernel = new CapturingKernel('/app');
        $kernel->bootstrap($container);

        $kernel->handle($this->createStub(ServerRequestInterface::class));

        // Act
        $kernel->terminate();

        // Assert
        $this->assertTrue($fired);
    }

    public function testResponseSentCarriesRequestAndResponse(): void
    {
        // Arrange
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

        $container = $this->buildContainer($provider);
        $kernel = new CapturingKernel('/app');
        $kernel->bootstrap($container);

        $request = $this->createStub(ServerRequestInterface::class);
        $kernel->handle($request);

        // Act
        $kernel->terminate();

        // Assert
        $this->assertNotNull($capturedRequest);
        $this->assertNotNull($capturedResponse);
        $this->assertSame(200, $capturedResponse->getStatusCode());
    }

    // -----------------------------------------------------------
    // No dispatcher registered — no errors
    // -----------------------------------------------------------

    public function testHandleWorksWithoutEventDispatcher(): void
    {
        // Arrange — container with no EventDispatcherInterface
        $container = $this->createStub(Application::class);
        $container->method('has')->willReturn(false);
        $container->method('get')->willReturn(
            $this->createStub(Bootstrapper::class),
        );

        $kernel = new CapturingKernel('/app');
        $kernel->bootstrap($container);

        // Act — should not throw
        $response = $kernel->handle($this->createStub(ServerRequestInterface::class));

        // Assert
        $this->assertSame(200, $response->getStatusCode());
    }
}
