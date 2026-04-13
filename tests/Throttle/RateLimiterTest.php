<?php

declare(strict_types=1);

namespace Arcanum\Test\Throttle;

use Arcanum\Throttle\Quota;
use Arcanum\Throttle\RateLimiter;
use Arcanum\Throttle\SlidingWindow;
use Arcanum\Throttle\TokenBucket;
use Arcanum\Vault\ArrayDriver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Log\LoggerInterface;

#[CoversClass(RateLimiter::class)]
#[UsesClass(Quota::class)]
#[UsesClass(TokenBucket::class)]
#[UsesClass(ArrayDriver::class)]
final class RateLimiterTest extends TestCase
{
    public function testDefaultsToTokenBucketStrategy(): void
    {
        $limiter = new RateLimiter(new ArrayDriver());

        $quota = $limiter->attempt('test', 5, 60);

        $this->assertTrue($quota->isAllowed());
        $this->assertSame(4, $quota->remaining);
    }

    public function testAcceptsCustomStrategy(): void
    {
        $limiter = new RateLimiter(new ArrayDriver(), new SlidingWindow());

        $quota = $limiter->attempt('test', 5, 60);

        $this->assertTrue($quota->isAllowed());
        $this->assertSame(4, $quota->remaining);
    }

    public function testDelegatesLimitEnforcementToStrategy(): void
    {
        $limiter = new RateLimiter(new ArrayDriver());

        for ($i = 0; $i < 5; $i++) {
            $limiter->attempt('test', 5, 60);
        }

        $quota = $limiter->attempt('test', 5, 60);

        $this->assertFalse($quota->isAllowed());
    }

    public function testLogsRateCheckPassed(): void
    {
        // Arrange
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('debug')
            ->with('Rate check passed', [
                'key' => 'user-ip',
                'remaining' => 4,
            ]);

        $limiter = new RateLimiter(new ArrayDriver(), new TokenBucket(), $logger);

        // Act
        $limiter->attempt('user-ip', 5, 60);
    }

    public function testLogsRateLimitExceeded(): void
    {
        // Arrange
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('notice')
            ->with('Rate limit exceeded', $this->callback(function (array $context): bool {
                return $context['key'] === 'user-ip'
                    && $context['limit'] === 3
                    && isset($context['retry_after']);
            }));

        $limiter = new RateLimiter(new ArrayDriver(), new TokenBucket(), $logger);

        // Exhaust the limit
        $limiter->attempt('user-ip', 3, 60);
        $limiter->attempt('user-ip', 3, 60);
        $limiter->attempt('user-ip', 3, 60);

        // Act — this attempt should be rejected
        $limiter->attempt('user-ip', 3, 60);
    }
}
