<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Hyper\CallableHandler;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

#[CoversClass(CallableHandler::class)]
final class CallableHandlerTest extends TestCase
{
    public function testHandleDelegatesToClosure(): void
    {
        // Arrange
        $request = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $handler = new CallableHandler(
            fn(ServerRequestInterface $r): ResponseInterface => $response
        );

        // Act
        $result = $handler->handle($request);

        // Assert
        $this->assertSame($response, $result);
    }

    public function testClosureReceivesExactRequestObject(): void
    {
        // Arrange
        $request = $this->createStub(ServerRequestInterface::class);
        $captured = null;

        $handler = new CallableHandler(
            function (ServerRequestInterface $r) use (&$captured): ResponseInterface {
                $captured = $r;
                return $this->createStub(ResponseInterface::class);
            }
        );

        // Act
        $handler->handle($request);

        // Assert
        $this->assertSame($request, $captured);
    }
}
