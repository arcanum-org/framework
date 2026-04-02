<?php

declare(strict_types=1);

namespace Arcanum\Test\Shodo\Helpers;

use Arcanum\Shodo\Helpers\FormatHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FormatHelper::class)]
final class FormatHelperTest extends TestCase
{
    public function testNumberWithDefaults(): void
    {
        // Arrange
        $helper = new FormatHelper();

        // Act
        $result = $helper->number(1234567);

        // Assert
        $this->assertSame('1,234,567', $result);
    }

    public function testNumberWithDecimals(): void
    {
        // Arrange
        $helper = new FormatHelper();

        // Act
        $result = $helper->number(1234.5, 2);

        // Assert
        $this->assertSame('1,234.50', $result);
    }

    public function testNumberWithCustomSeparators(): void
    {
        // Arrange
        $helper = new FormatHelper();

        // Act
        $result = $helper->number(1234567.89, 2, ',', '.');

        // Assert
        $this->assertSame('1.234.567,89', $result);
    }

    public function testNumberWithNegativeValue(): void
    {
        // Arrange
        $helper = new FormatHelper();

        // Act
        $result = $helper->number(-42.5, 1);

        // Assert
        $this->assertSame('-42.5', $result);
    }

    public function testNumberWithZero(): void
    {
        // Arrange
        $helper = new FormatHelper();

        // Act
        $result = $helper->number(0, 2);

        // Assert
        $this->assertSame('0.00', $result);
    }

    public function testDateFromIntTimestamp(): void
    {
        // Arrange
        $helper = new FormatHelper();
        $timestamp = (new \DateTimeImmutable('2025-03-15'))->getTimestamp();

        // Act
        $result = $helper->date($timestamp, 'Y-m-d');

        // Assert
        $this->assertSame('2025-03-15', $result);
    }

    public function testDateFromString(): void
    {
        // Arrange
        $helper = new FormatHelper();

        // Act
        $result = $helper->date('2025-03-15', 'M j, Y');

        // Assert
        $this->assertSame('Mar 15, 2025', $result);
    }

    public function testDateFromDateTimeInterface(): void
    {
        // Arrange
        $helper = new FormatHelper();
        $dt = new \DateTimeImmutable('2025-03-15');

        // Act
        $result = $helper->date($dt, 'Y-m-d');

        // Assert
        $this->assertSame('2025-03-15', $result);
    }

    public function testDateWithDefaultFormat(): void
    {
        // Arrange
        $helper = new FormatHelper();

        // Act
        $result = $helper->date('2025-03-15');

        // Assert
        $this->assertSame('Mar 15, 2025', $result);
    }

    public function testDateWithCustomFormat(): void
    {
        // Arrange
        $helper = new FormatHelper();

        // Act
        $result = $helper->date('2025-12-25', 'D, d M Y');

        // Assert
        $this->assertSame('Thu, 25 Dec 2025', $result);
    }
}
