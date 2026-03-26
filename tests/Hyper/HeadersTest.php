<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper;

use Arcanum\Hyper\Headers;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Gather\IgnoreCaseRegistry;
use Arcanum\Gather\Registry;

#[CoversClass(Headers::class)]
#[UsesClass(IgnoreCaseRegistry::class)]
#[UsesClass(Registry::class)]
final class HeadersTest extends TestCase
{
    public function testHeaders(): void
    {
        // Arrange
        $headers = new Headers([
            'Host' => 'en.wikipedia.org:8080',
            'Origin' => 'http://www.example-social-network.com',
        ]);

        // Act
        $host = $headers->get('hOst');
        $origin = $headers->get('origiN');

        // Assert
        $this->assertEquals(['en.wikipedia.org:8080'], $host);
        $this->assertEquals(['http://www.example-social-network.com'], $origin);
    }

    public function testSetHeader(): void
    {
        // Arrange
        $headers = new Headers([
            'Host' => 'en.wikipedia.org:8080',
            'Origin' => 'http://www.example-social-network.com',
        ]);

        // Act
        $headers->set('Host', 'example.com');
        $headers->set('Origin', 'http://www.example-antisocial-network.com');

        // Assert
        $this->assertEquals(['example.com'], $headers->get('hOst'));
        $this->assertEquals(['http://www.example-antisocial-network.com'], $headers->get('Origin'));
    }

    public function testSetHeaderOffset(): void
    {
        // Arrange
        $headers = new Headers([
            'Host' => 'en.wikipedia.org:8080',
            'Origin' => 'http://www.example-social-network.com',
        ]);

        // Act
        $headers['Host'] = 'example.com';
        $headers['Origin'] = 'http://www.example-antisocial-network.com';

        // Assert
        $this->assertEquals(['example.com'], $headers->get('hOst'));
        $this->assertEquals(['http://www.example-antisocial-network.com'], $headers->get('Origin'));
    }

    public function testSerialize(): void
    {
        // Arrange
        $headers = new Headers([
            'Host' => 'en.wikipedia.org:8080',
            'Origin' => 'http://www.example-social-network.com',
        ]);

        // Act
        $serialized = serialize($headers);
        $unserialized = unserialize($serialized);

        // Assert
        $this->assertEquals($headers, $unserialized);
    }

    public function testHeaderSetThrowsInvalidArgumentExceptionIfKeyIsNotString(): void
    {
        // Arrange
        $headers = new Headers([
            'Host' => 'en.wikipedia.org:8080',
            'Origin' => 'http://www.example-social-network.com',
        ]);

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $headers[1] = 'example.com'; /** @phpstan-ignore-line */
    }

    public function testHeaderSetThrowsInvalidArgumentExceptionIfHeaderNameIsInvalidFormat(): void
    {
        // Arrange
        $headers = new Headers([
            'Host' => 'en.wikipedia.org:8080',
            'Origin' => 'http://www.example-social-network.com',
        ]);

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $headers['Host:'] = 'example.com';
    }

    public function testHeaderSetThrowsInvalidArgumentExceptionIfHeaderValueIsNotString(): void
    {
        // Arrange
        $headers = new Headers([
            'Host' => 'en.wikipedia.org:8080',
            'Origin' => 'http://www.example-social-network.com',
        ]);

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $headers['Host'] = 1;
    }

    public function testHeaderSetThrowsInvalidArgumentExceptionIfHeaderValueIsEmpty(): void
    {
        // Arrange
        $headers = new Headers([
            'Host' => 'en.wikipedia.org:8080',
            'Origin' => 'http://www.example-social-network.com',
        ]);

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $headers['Host'] = '';
    }

    public function testHeaderAcceptsZeroStringValue(): void
    {
        // Arrange & Act
        $headers = new Headers([
            'Content-Length' => '0',
        ]);

        // Assert
        $this->assertEquals(['0'], $headers->get('Content-Length'));
    }

    public function testHeaderSetThrowsInvalidArgumentExceptionIfHeaderValueContainsInvalidCharacters(): void
    {
        // Arrange
        $headers = new Headers([
            'Host' => 'en.wikipedia.org:8080',
            'Origin' => 'http://www.example-social-network.com',
        ]);

        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        $headers['Host'] = 'example.com' . chr(127);
    }
}
