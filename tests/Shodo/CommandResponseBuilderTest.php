<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo;

use Arcanum\Flow\Conveyor\EmptyDTO;
use Arcanum\Flow\River\EmptyStream;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Message;
use Arcanum\Hyper\Response;
use Arcanum\Shodo\CommandResponseBuilder;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(CommandResponseBuilder::class)]
#[UsesClass(EmptyDTO::class)]
#[UsesClass(EmptyStream::class)]
#[UsesClass(Headers::class)]
#[UsesClass(Message::class)]
#[UsesClass(Response::class)]
#[UsesClass(\Arcanum\Gather\IgnoreCaseRegistry::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
final class CommandResponseBuilderTest extends TestCase
{
    public function testVoidHandlerReturns204(): void
    {
        // Arrange
        $builder = new CommandResponseBuilder();

        // Act
        $response = $builder->build(new EmptyDTO());

        // Assert
        $this->assertSame(204, $response->getStatusCode());
    }

    public function testVoidHandlerReturnsEmptyBody(): void
    {
        // Arrange
        $builder = new CommandResponseBuilder();

        // Act
        $response = $builder->build(new EmptyDTO());

        // Assert
        $this->assertSame('', (string) $response->getBody());
    }

    public function testScalarResultReturns201(): void
    {
        // Arrange — handler returned a string ID, wrapped in an object by Conveyor
        $builder = new CommandResponseBuilder();
        $result = new class {
            public string $id = 'order-abc-123';
        };

        // Act
        $response = $builder->build($result);

        // Assert
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testDtoResultReturns201(): void
    {
        // Arrange
        $builder = new CommandResponseBuilder();
        $result = new class {
            public function __construct(
                public readonly string $id = 'abc',
                public readonly string $status = 'created',
            ) {
            }
        };

        // Act
        $response = $builder->build($result);

        // Assert
        $this->assertSame(201, $response->getStatusCode());
    }

    public function testCreatedResponseHasEmptyBody(): void
    {
        // Arrange
        $builder = new CommandResponseBuilder();
        $result = new class {
            public string $id = 'order-abc-123';
        };

        // Act
        $response = $builder->build($result);

        // Assert
        $this->assertSame('', (string) $response->getBody());
    }

    public function testResponseIsValidResponseInterface(): void
    {
        // Arrange
        $builder = new CommandResponseBuilder();

        // Act
        $response = $builder->build(new EmptyDTO());

        // Assert
        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $response);
    }
}
