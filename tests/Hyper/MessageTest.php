<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Hyper\Message;
use Arcanum\Hyper\Headers;
use Arcanum\Hyper\Version;
use Psr\Http\Message\StreamInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Message::class)]
#[UsesClass(Version::class)]
final class MessageTest extends TestCase
{
    public function testMessageGetProtocolVersion(): void
    {
        // Arrange
        $headers = $this->createStub(Headers::class);

        $body = $this->createStub(StreamInterface::class);

        $message = new Message($headers, $body, Version::from('1.1'));

        // Act
        $version = $message->getProtocolVersion();

        // Assert
        $this->assertSame('1.1', $version);
    }

    public function testWithProtocolVersion(): void
    {
        // Arrange
        $headers = $this->createStub(Headers::class);

        $body = $this->createStub(StreamInterface::class);

        $message = new Message($headers, $body, Version::from('1.1'));

        // Act
        $newMessage = $message->withProtocolVersion('2.0');

        // Assert
        $this->assertSame('1.1', $message->getProtocolVersion());
        $this->assertSame('2.0', $newMessage->getProtocolVersion());
    }

    public function testGetHeaders(): void
    {
        // Arrange
        /** @var Headers&\PHPUnit\Framework\MockObject\MockObject */
        $headers = $this->getMockBuilder(Headers::class)
            ->disableOriginalConstructor()
            ->getMock();

        $body = $this->createStub(StreamInterface::class);

        $headers->expects($this->once())
            ->method('toArray')
            ->willReturn(['foo' => ['bar']]);

        $message = new Message($headers, $body, Version::from('1.1'));

        // Act
        $result = $message->getHeaders();

        // Assert
        $this->assertSame(['foo' => ['bar']], $result);
    }

    public function testGetHeader(): void
    {
        // Arrange
        /** @var Headers&\PHPUnit\Framework\MockObject\MockObject */
        $headers = $this->getMockBuilder(Headers::class)
            ->disableOriginalConstructor()
            ->getMock();

        $body = $this->createStub(StreamInterface::class);

        $headers->expects($this->once())
            ->method('get')
            ->with('foo')
            ->willReturn(['bar']);

        $message = new Message($headers, $body, Version::from('1.1'));

        // Act
        $result = $message->getHeader('foo');

        // Assert
        $this->assertSame(['bar'], $result);
    }

    public function testGetHeaderLine(): void
    {
        // Arrange
        /** @var Headers&\PHPUnit\Framework\MockObject\MockObject */
        $headers = $this->getMockBuilder(Headers::class)
            ->disableOriginalConstructor()
            ->getMock();

        $body = $this->createStub(StreamInterface::class);

        $headers->expects($this->once())
            ->method('get')
            ->with('foo')
            ->willReturn(['bar', 'baz']);

        $message = new Message($headers, $body, Version::from('1.1'));

        // Act
        $result = $message->getHeaderLine('foo');

        // Assert
        $this->assertSame('bar,baz', $result);
    }

    public function testHasHeader(): void
    {
        // Arrange
        /** @var Headers&\PHPUnit\Framework\MockObject\MockObject */
        $headers = $this->getMockBuilder(Headers::class)
            ->disableOriginalConstructor()
            ->getMock();

        $body = $this->createStub(StreamInterface::class);

        $headers->expects($this->once())
            ->method('has')
            ->with('foo')
            ->willReturn(true);

        $message = new Message($headers, $body, Version::from('1.1'));

        // Act
        $result = $message->hasHeader('foo');

        // Assert
        $this->assertTrue($result);
    }

    public function testWithHeader(): void
    {
        // Arrange
        /** @var Headers&\PHPUnit\Framework\MockObject\MockObject */
        $headers = $this->getMockBuilder(Headers::class)
            ->disableOriginalConstructor()
            ->getMock();

        $body = $this->createStub(StreamInterface::class);

        $headers->expects($this->once())
            ->method('offsetSet')
            ->with('foo', ['bar'])
            ->willReturnSelf();

        $message = new Message($headers, $body, Version::from('1.1'));

        // Act
        $newMessage = $message->withHeader('foo', 'bar');

        // Assert
        $this->assertNotSame($message, $newMessage);
    }

    public function testWithAddedHeader(): void
    {
        // Arrange
        /** @var Headers&\PHPUnit\Framework\MockObject\MockObject */
        $headers = $this->getMockBuilder(Headers::class)
            ->disableOriginalConstructor()
            ->getMock();

        $body = $this->createStub(StreamInterface::class);

        $headers->expects($this->exactly(2))
            ->method('get')
            ->with('foo')
            ->willReturnOnConsecutiveCalls(null, ['bar']);

        $setMatcher = $this->exactly(2);
        $headers->expects($setMatcher)
            ->method('set')
            ->willReturnCallback(function ($name, $value) use ($setMatcher) {
                switch ($setMatcher->numberOfInvocations()) {
                    case 1:
                        $this->assertSame('foo', $name);
                        $this->assertSame(['bar'], $value);
                        break;
                    case 2:
                        $this->assertSame('foo', $name);
                        $this->assertSame(['bar', 'baz', 'qux'], $value);
                        break;
                }
            });

        $message = new Message($headers, $body, Version::from('1.1'));

        // Act
        $newMessage = $message->withAddedHeader('foo', 'bar');
        $newMessage = $newMessage->withAddedHeader('foo', ['baz', 'qux']);

        // Assert
        $this->assertNotSame($message, $newMessage);
    }

    public function testWithoutHeader(): void
    {
        // Arrange
        /** @var Headers&\PHPUnit\Framework\MockObject\MockObject */
        $headers = $this->getMockBuilder(Headers::class)
            ->disableOriginalConstructor()
            ->getMock();

        $body = $this->createStub(StreamInterface::class);

        $headers->expects($this->once())
            ->method('offsetUnset')
            ->with('foo')
            ->willReturnSelf();

        $message = new Message($headers, $body, Version::from('1.1'));

        // Act
        $newMessage = $message->withoutHeader('foo');

        // Assert
        $this->assertNotSame($message, $newMessage);
    }

    public function testGetBody(): void
    {
        // Arrange
        $headers = $this->createStub(Headers::class);

        $body = $this->createStub(StreamInterface::class);

        $message = new Message($headers, $body, Version::from('1.1'));

        // Act
        $result = $message->getBody();

        // Assert
        $this->assertSame($body, $result);
    }

    public function testWithBody(): void
    {
        // Arrange
        $headers = $this->createStub(Headers::class);

        $body = $this->createStub(StreamInterface::class);

        $message = new Message($headers, $body, Version::from('1.1'));

        $newBody = $this->createStub(StreamInterface::class);

        // Act
        $newMessage = $message->withBody($newBody);

        // Assert
        $this->assertNotSame($message, $newMessage);
        $this->assertNotSame($message->getBody(), $newMessage->getBody());
        $this->assertSame($newBody, $newMessage->getBody());
    }

    public function testWithBodyChangesNothingIfSameBodyPassedIn(): void
    {
        // Arrange
        $headers = $this->createStub(Headers::class);

        $body = $this->createStub(StreamInterface::class);

        $message = new Message($headers, $body, Version::from('1.1'));

        // Act
        $newMessage = $message->withBody($body);

        // Assert
        $this->assertSame($message, $newMessage);
        $this->assertSame($message->getBody(), $newMessage->getBody());
    }
}
