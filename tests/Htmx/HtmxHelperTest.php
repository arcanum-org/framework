<?php

declare(strict_types=1);

namespace Arcanum\Test\Htmx;

use Arcanum\Htmx\HtmxHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(HtmxHelper::class)]
final class HtmxHelperTest extends TestCase
{
    public function testScriptRendersFullTag(): void
    {
        // Arrange
        $helper = new HtmxHelper(
            '4.0.0-beta1',
            'https://unpkg.com/htmx.org@{version}/dist/htmx.min.js',
            'sha384-abc123',
        );

        // Act
        $html = $helper->script();

        // Assert
        $this->assertStringContainsString('src="https://unpkg.com/htmx.org@4.0.0-beta1/dist/htmx.min.js"', $html);
        $this->assertStringContainsString('integrity="sha384-abc123"', $html);
        $this->assertStringContainsString('crossorigin="anonymous"', $html);
        $this->assertStringStartsWith('<script ', $html);
        $this->assertStringEndsWith('></script>', $html);
    }

    public function testScriptOmitsIntegrityWhenEmpty(): void
    {
        // Arrange
        $helper = new HtmxHelper(
            '4.0.0-beta1',
            'https://cdn.example.com/htmx@{version}.js',
            '',
        );

        // Act
        $html = $helper->script();

        // Assert
        $this->assertStringContainsString('src="https://cdn.example.com/htmx@4.0.0-beta1.js"', $html);
        $this->assertStringNotContainsString('integrity', $html);
        $this->assertStringNotContainsString('crossorigin', $html);
    }

    public function testCsrfRendersScriptTag(): void
    {
        // Arrange
        $helper = new HtmxHelper('4.0.0-beta1', '', '');

        // Act
        $html = $helper->csrf();

        // Assert
        $this->assertSame('<script src="/_htmx/csrf.js"></script>', $html);
    }
}
