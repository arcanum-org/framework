<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor;

use Arcanum\Flow\Conveyor\DynamicDTO;
use Arcanum\Flow\Conveyor\HandlerProxy;
use Arcanum\Flow\Conveyor\Page;
use Arcanum\Gather\Registry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Page::class)]
#[UsesClass(DynamicDTO::class)]
#[UsesClass(Registry::class)]
final class PageTest extends TestCase
{
    public function testImplementsHandlerProxy(): void
    {
        // Arrange & Act
        $page = new Page('App\\Pages\\About');

        // Assert
        $this->assertInstanceOf(HandlerProxy::class, $page);
    }

    public function testHandlerBaseNameIsFixedToPageClass(): void
    {
        // Arrange
        $page = new Page('App\\Pages\\About');

        // Act & Assert — always routes to PageHandler, not AboutHandler
        $this->assertSame(Page::class, $page->handlerBaseName());
    }

    public function testHandlerBaseNameDoesNotVaryByDtoClass(): void
    {
        // Arrange
        $about = new Page('App\\Pages\\About');
        $contact = new Page('App\\Pages\\Contact');

        // Assert — both route to the same handler
        $this->assertSame($about->handlerBaseName(), $contact->handlerBaseName());
    }

    public function testDtoClassReturnsVirtualClassName(): void
    {
        // Arrange
        $page = new Page('App\\Pages\\About');

        // Act & Assert
        $this->assertSame('App\\Pages\\About', $page->dtoClass());
    }

    public function testToArrayReturnsData(): void
    {
        // Arrange
        $data = ['title' => 'About Us', 'year' => 2024];
        $page = new Page('App\\Pages\\About', $data);

        // Act & Assert
        $this->assertSame($data, $page->toArray());
    }

    public function testToArrayReturnsEmptyForStaticPage(): void
    {
        // Arrange
        $page = new Page('App\\Pages\\About');

        // Act & Assert
        $this->assertSame([], $page->toArray());
    }

    public function testGetReturnsValue(): void
    {
        // Arrange
        $page = new Page('App\\Pages\\About', ['title' => 'About Us']);

        // Act & Assert
        $this->assertSame('About Us', $page->get('title'));
    }

    public function testHasChecksExistence(): void
    {
        // Arrange
        $page = new Page('App\\Pages\\About', ['title' => 'About']);

        // Act & Assert
        $this->assertTrue($page->has('title'));
        $this->assertFalse($page->has('missing'));
    }

    public function testCoercibleMethodsDelegateToRegistry(): void
    {
        // Arrange
        $page = new Page('App\\Pages\\About', [
            'count' => '5',
            'enabled' => true,
            'price' => '9.99',
        ]);

        // Act & Assert
        $this->assertSame(5, $page->asInt('count'));
        $this->assertTrue($page->asBool('enabled'));
        $this->assertSame(9.99, $page->asFloat('price'));
        $this->assertSame('5', $page->asString('count'));
    }
}
