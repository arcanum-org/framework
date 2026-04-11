<?php

declare(strict_types=1);

namespace Arcanum\Test\Session;

use Arcanum\Session\Flash;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Flash::class)]
final class FlashTest extends TestCase
{
    public function testPreviousNextDataBecomesCurrentData(): void
    {
        $flash = new Flash(['success' => 'Item saved.']);

        $this->assertSame('Item saved.', $flash->get('success'));
    }

    public function testHasReturnsTrueForCurrentData(): void
    {
        $flash = new Flash(['key' => 'value']);

        $this->assertTrue($flash->has('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $flash = new Flash();

        $this->assertFalse($flash->has('missing'));
    }

    public function testGetReturnsDefaultForMissingKey(): void
    {
        $flash = new Flash();

        $this->assertSame('fallback', $flash->get('missing', 'fallback'));
    }

    public function testGetReturnsEmptyStringDefaultByDefault(): void
    {
        $flash = new Flash();

        $this->assertSame('', $flash->get('missing'));
    }

    public function testSetQueuesForNextRequest(): void
    {
        $flash = new Flash();

        $flash->set('error', 'Something failed.');

        $this->assertFalse($flash->has('error'));
        $this->assertSame(['error' => 'Something failed.'], $flash->pending());
    }

    public function testAllReturnsCurrentData(): void
    {
        $flash = new Flash(['a' => '1', 'b' => '2']);

        $this->assertSame(['a' => '1', 'b' => '2'], $flash->all());
    }

    public function testPendingReturnsOnlyNextData(): void
    {
        $flash = new Flash(['current' => 'old']);

        $flash->set('next', 'new');

        $this->assertSame(['next' => 'new'], $flash->pending());
    }

    public function testEmptyFlashHasNoCurrentOrPendingData(): void
    {
        $flash = new Flash();

        $this->assertSame([], $flash->all());
        $this->assertSame([], $flash->pending());
    }
}
