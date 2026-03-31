<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor;

use Arcanum\Flow\Conveyor\DynamicDTO;
use Arcanum\Flow\Conveyor\Page;
use Arcanum\Flow\Conveyor\PageHandler;
use Arcanum\Gather\Registry;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(PageHandler::class)]
#[UsesClass(Page::class)]
#[UsesClass(DynamicDTO::class)]
#[UsesClass(Registry::class)]
final class PageHandlerTest extends TestCase
{
    public function testReturnsEmptyArrayForStaticPage(): void
    {
        // Arrange
        $handler = new PageHandler();
        $page = new Page('App\\Pages\\About');

        // Act
        $result = $handler($page);

        // Assert
        $this->assertSame([], $result);
    }

    public function testReturnsDataForPageWithContent(): void
    {
        // Arrange
        $handler = new PageHandler();
        $data = ['title' => 'About Us', 'message' => 'Welcome'];
        $page = new Page('App\\Pages\\About', $data);

        // Act
        $result = $handler($page);

        // Assert
        $this->assertSame($data, $result);
    }
}
