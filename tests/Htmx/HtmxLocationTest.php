<?php

declare(strict_types=1);

namespace Arcanum\Test\Htmx;

use Arcanum\Htmx\HtmxLocation;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HtmxLocation::class)]
final class HtmxLocationTest extends TestCase
{
    public function testSimplePathOnly(): void
    {
        // Arrange
        $location = new HtmxLocation('/dashboard');

        // Act
        $json = $location->toJson();

        // Assert — only path, no extra fields
        $decoded = json_decode($json, true);
        $this->assertSame(['path' => '/dashboard'], $decoded);
    }

    public function testFullEnvelope(): void
    {
        // Arrange
        $location = new HtmxLocation(
            path: '/products',
            target: '#main',
            swap: 'outerHTML',
            source: '#nav-link',
            event: 'click',
            handler: 'customHandler',
            values: ['page' => 2, 'sort' => 'name'],
            headers: ['X-Custom' => 'value'],
            select: '.content',
        );

        // Act
        $serialized = $location->jsonSerialize();

        // Assert
        $this->assertSame('/products', $serialized['path']);
        $this->assertSame('#main', $serialized['target']);
        $this->assertSame('outerHTML', $serialized['swap']);
        $this->assertSame('#nav-link', $serialized['source']);
        $this->assertSame('click', $serialized['event']);
        $this->assertSame('customHandler', $serialized['handler']);
        $this->assertSame(['page' => 2, 'sort' => 'name'], $serialized['values']);
        $this->assertSame(['X-Custom' => 'value'], $serialized['headers']);
        $this->assertSame('.content', $serialized['select']);
    }

    public function testPartialFieldsOmitsNulls(): void
    {
        // Arrange
        $location = new HtmxLocation('/page', target: '#sidebar');

        // Act
        $decoded = json_decode($location->toJson(), true);

        // Assert — only path and target, no null fields
        $this->assertSame(['path' => '/page', 'target' => '#sidebar'], $decoded);
    }

    public function testJsonSerializable(): void
    {
        // Arrange
        $location = new HtmxLocation('/test', swap: 'innerHTML');

        // Act
        $serialized = $location->jsonSerialize();

        // Assert
        $this->assertSame('/test', $serialized['path']);
        $this->assertSame('innerHTML', $serialized['swap']);
        $this->assertArrayNotHasKey('target', $serialized);
    }

    public function testSlashesNotEscaped(): void
    {
        // Arrange
        $location = new HtmxLocation('/path/to/resource');

        // Act
        $json = $location->toJson();

        // Assert — paths should be readable, not escaped
        $this->assertStringContainsString('/path/to/resource', $json);
        $this->assertStringNotContainsString('\/', $json);
    }
}
