<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Helpers;

use Arcanum\Shodo\Helpers\StrHelper;
use Arcanum\Toolkit\Strings;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(StrHelper::class)]
#[UsesClass(Strings::class)]
final class StrHelperTest extends TestCase
{
    public function testTextShorterThanLimitIsUnchanged(): void
    {
        // Arrange
        $helper = new StrHelper();

        // Act
        $result = $helper->truncate('hello', 10);

        // Assert
        $this->assertSame('hello', $result);
    }

    public function testTextExactlyAtLimitIsUnchanged(): void
    {
        // Arrange
        $helper = new StrHelper();

        // Act
        $result = $helper->truncate('hello', 5);

        // Assert
        $this->assertSame('hello', $result);
    }

    public function testTruncationWithDefaultSuffix(): void
    {
        // Arrange
        $helper = new StrHelper();

        // Act
        $result = $helper->truncate('Hello, world!', 10);

        // Assert
        $this->assertSame('Hello, ...', $result);
        $this->assertSame(10, mb_strlen($result));
    }

    public function testTruncationWithCustomSuffix(): void
    {
        // Arrange
        $helper = new StrHelper();

        // Act
        $result = $helper->truncate('Hello, world!', 10, '~');

        // Assert
        $this->assertSame('Hello, wo~', $result);
        $this->assertSame(10, mb_strlen($result));
    }

    public function testTruncationWithMultibyteText(): void
    {
        // Arrange
        $helper = new StrHelper();

        // Act
        $result = $helper->truncate('こんにちは世界', 5, '…');

        // Assert
        $this->assertSame('こんにち…', $result);
        $this->assertSame(5, mb_strlen($result));
    }

    public function testEmptyString(): void
    {
        // Arrange
        $helper = new StrHelper();

        // Act
        $result = $helper->truncate('', 10);

        // Assert
        $this->assertSame('', $result);
    }

    public function testLower(): void
    {
        // Arrange
        $helper = new StrHelper();

        // Act
        $result = $helper->lower('Hello WORLD');

        // Assert
        $this->assertSame('hello world', $result);
    }

    public function testUpper(): void
    {
        // Arrange
        $helper = new StrHelper();

        // Act
        $result = $helper->upper('Hello world');

        // Assert
        $this->assertSame('HELLO WORLD', $result);
    }

    public function testTitle(): void
    {
        // Arrange
        $helper = new StrHelper();

        // Act
        $result = $helper->title('hello world');

        // Assert
        $this->assertSame('Hello World', $result);
    }

    public function testKebab(): void
    {
        // Arrange
        $helper = new StrHelper();

        // Act
        $result = $helper->kebab('HelloWorld');

        // Assert
        $this->assertSame('hello-world', $result);
    }
}
