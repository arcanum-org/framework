<?php

declare(strict_types=1);

namespace Arcanum\Test\Throttle;

use Arcanum\Throttle\Quota;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Quota::class)]
final class QuotaTest extends TestCase
{
    public function testAllowedQuotaReportsIsAllowed(): void
    {
        $quota = new Quota(allowed: true, remaining: 9, limit: 10, resetAt: 1000);

        $this->assertTrue($quota->isAllowed());
        $this->assertSame(9, $quota->remaining);
        $this->assertSame(10, $quota->limit);
        $this->assertSame(1000, $quota->resetAt);
    }

    public function testDeniedQuotaReportsNotAllowed(): void
    {
        $quota = new Quota(allowed: false, remaining: 0, limit: 10, resetAt: 1000);

        $this->assertFalse($quota->isAllowed());
    }

    public function testHeadersOnAllowedRequest(): void
    {
        $quota = new Quota(allowed: true, remaining: 7, limit: 10, resetAt: 1700000000);

        $headers = $quota->headers();

        $this->assertSame('10', $headers['X-RateLimit-Limit']);
        $this->assertSame('7', $headers['X-RateLimit-Remaining']);
        $this->assertSame('1700000000', $headers['X-RateLimit-Reset']);
        $this->assertArrayNotHasKey('Retry-After', $headers);
    }

    public function testHeadersOnDeniedRequestIncludesRetryAfter(): void
    {
        $quota = new Quota(
            allowed: false,
            remaining: 0,
            limit: 10,
            resetAt: 1700000030,
            retryAfter: 30,
        );

        $headers = $quota->headers();

        $this->assertSame('10', $headers['X-RateLimit-Limit']);
        $this->assertSame('0', $headers['X-RateLimit-Remaining']);
        $this->assertSame('1700000030', $headers['X-RateLimit-Reset']);
        $this->assertSame('30', $headers['Retry-After']);
    }

    public function testRemainingNeverNegativeInHeaders(): void
    {
        $quota = new Quota(allowed: false, remaining: -1, limit: 5, resetAt: 1000);

        $headers = $quota->headers();

        $this->assertSame('0', $headers['X-RateLimit-Remaining']);
    }

    public function testExplicitRetryAfterIsUsedInHeaders(): void
    {
        $quota = new Quota(
            allowed: false,
            remaining: 0,
            limit: 10,
            resetAt: 1700000000,
            retryAfter: 42,
        );

        $headers = $quota->headers();

        $this->assertSame('42', $headers['Retry-After']);
    }

    public function testRetryAfterDefaultsToZeroWhenNotPassed(): void
    {
        // A denied Quota constructed without retryAfter renders Retry-After: 0.
        // In practice every framework Throttler passes the value explicitly;
        // this test pins the contract for any third-party code that doesn't.
        $quota = new Quota(allowed: false, remaining: 0, limit: 10, resetAt: 1700000000);

        $headers = $quota->headers();

        $this->assertSame('0', $headers['Retry-After']);
    }
}
