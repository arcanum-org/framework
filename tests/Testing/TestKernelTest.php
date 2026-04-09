<?php

declare(strict_types=1);

namespace Arcanum\Test\Testing;

use Arcanum\Auth\ActiveIdentity;
use Arcanum\Auth\SimpleIdentity;
use Arcanum\Cabinet\Application;
use Arcanum\Hourglass\Clock;
use Arcanum\Hourglass\FrozenClock;
use Arcanum\Testing\TestKernel;
use Arcanum\Vault\ArrayDriver;
use DateInterval;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(TestKernel::class)]
final class TestKernelTest extends TestCase
{
    public function testDefaultsBindFrozenClockAndArrayDriverAndActiveIdentity(): void
    {
        $kernel = new TestKernel();

        $this->assertInstanceOf(FrozenClock::class, $kernel->clock());
        $this->assertSame(
            '2026-01-01T00:00:00+00:00',
            $kernel->clock()->now()->format(DATE_ATOM),
        );
        $this->assertInstanceOf(ArrayDriver::class, $kernel->cache());
        $this->assertNull($kernel->rootDirectory());
    }

    public function testContainerExposesBoundServices(): void
    {
        $kernel = new TestKernel();
        $container = $kernel->container();

        $this->assertInstanceOf(Application::class, $container);
        $this->assertSame($kernel->clock(), $container->get(Clock::class));
        $this->assertSame($kernel->cache(), $container->get(CacheInterface::class));
        $this->assertInstanceOf(ActiveIdentity::class, $container->get(ActiveIdentity::class));
    }

    public function testConstructorOverridesAreRespected(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2030-06-15T12:34:56+00:00'));
        $cache = new ArrayDriver($clock);

        $kernel = new TestKernel(clock: $clock, cache: $cache, rootDirectory: '/tmp/app');

        $this->assertSame($clock, $kernel->clock());
        $this->assertSame($cache, $kernel->cache());
        $this->assertSame('/tmp/app', $kernel->rootDirectory());
    }

    public function testActingAsBindsIdentityAndChains(): void
    {
        $kernel = new TestKernel();
        $alice = new SimpleIdentity('alice');

        $result = $kernel->actingAs($alice);

        $this->assertSame($kernel, $result);
        /** @var ActiveIdentity $active */
        $active = $kernel->container()->get(ActiveIdentity::class);
        $this->assertTrue($active->has());
        $this->assertSame($alice, $active->get());
    }

    public function testFrozenClockCanBeAdvancedThroughKernelHandle(): void
    {
        $kernel = new TestKernel();
        $clock = $kernel->clock();
        $this->assertInstanceOf(FrozenClock::class, $clock);

        $clock->advance(new DateInterval('PT1H'));

        $this->assertSame(
            '2026-01-01T01:00:00+00:00',
            $kernel->clock()->now()->format(DATE_ATOM),
        );
    }
}
