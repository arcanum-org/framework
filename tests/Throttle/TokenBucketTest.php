<?php

declare(strict_types=1);

namespace Arcanum\Test\Throttle;

use Arcanum\Throttle\Quota;
use Arcanum\Throttle\TokenBucket;
use Arcanum\Vault\ArrayDriver;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(TokenBucket::class)]
#[UsesClass(Quota::class)]
#[UsesClass(ArrayDriver::class)]
final class TokenBucketTest extends TestCase
{
    public function testFirstRequestIsAllowed(): void
    {
        $cache = new ArrayDriver();
        $bucket = new TokenBucket();

        $quota = $bucket->attempt($cache, 'test', 10, 60);

        $this->assertTrue($quota->isAllowed());
        $this->assertSame(9, $quota->remaining);
        $this->assertSame(10, $quota->limit);
    }

    public function testRequestsUpToLimitAreAllowed(): void
    {
        $cache = new ArrayDriver();
        $bucket = new TokenBucket();

        for ($i = 0; $i < 10; $i++) {
            $quota = $bucket->attempt($cache, 'test', 10, 60);
            $this->assertTrue($quota->isAllowed());
        }

        $this->assertSame(0, $quota->remaining);
    }

    public function testRequestBeyondLimitIsDenied(): void
    {
        $cache = new ArrayDriver();
        $bucket = new TokenBucket();

        for ($i = 0; $i < 10; $i++) {
            $bucket->attempt($cache, 'test', 10, 60);
        }

        $quota = $bucket->attempt($cache, 'test', 10, 60);

        $this->assertFalse($quota->isAllowed());
        $this->assertSame(0, $quota->remaining);
    }

    public function testDifferentKeysAreIndependent(): void
    {
        $cache = new ArrayDriver();
        $bucket = new TokenBucket();

        for ($i = 0; $i < 10; $i++) {
            $bucket->attempt($cache, 'user-a', 10, 60);
        }

        $quota = $bucket->attempt($cache, 'user-b', 10, 60);

        $this->assertTrue($quota->isAllowed());
        $this->assertSame(9, $quota->remaining);
    }

    public function testTokensRefillOverTime(): void
    {
        $cache = new ArrayDriver();
        $bucket = new TokenBucket();

        // Exhaust all tokens.
        for ($i = 0; $i < 5; $i++) {
            $bucket->attempt($cache, 'test', 5, 60);
        }

        $denied = $bucket->attempt($cache, 'test', 5, 60);
        $this->assertFalse($denied->isAllowed());

        // Simulate time passing by manipulating the cache entry directly.
        // Move lastRefill back so that enough tokens have refilled.
        /** @var array{tokens: float, lastRefill: int} $entry */
        $entry = $cache->get('test');
        $entry['lastRefill'] -= 60;
        $cache->set('test', $entry);

        $quota = $bucket->attempt($cache, 'test', 5, 60);

        $this->assertTrue($quota->isAllowed());
    }

    public function testTokensCapAtLimit(): void
    {
        $cache = new ArrayDriver();
        $bucket = new TokenBucket();

        // Simulate a long idle period by setting lastRefill far in the past.
        $cache->set('test', ['tokens' => 0.0, 'lastRefill' => time() - 3600]);

        $quota = $bucket->attempt($cache, 'test', 5, 60);

        $this->assertTrue($quota->isAllowed());
        // Tokens should cap at limit (5) minus one consumed = 4.
        $this->assertSame(4, $quota->remaining);
    }

    public function testResetAtIsInTheFuture(): void
    {
        $cache = new ArrayDriver();
        $bucket = new TokenBucket();
        $now = time();

        $quota = $bucket->attempt($cache, 'test', 10, 60);

        $this->assertGreaterThanOrEqual($now + 60, $quota->resetAt);
    }
}
