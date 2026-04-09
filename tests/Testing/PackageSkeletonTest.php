<?php

declare(strict_types=1);

namespace Arcanum\Test\Testing;

use Arcanum\Testing\Factory;
use Arcanum\Testing\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(TestKernel::class)]
#[CoversClass(Factory::class)]
final class PackageSkeletonTest extends TestCase
{
    public function testTestKernelClassLoads(): void
    {
        $kernel = new TestKernel();

        $this->assertInstanceOf(TestKernel::class, $kernel);
    }

    public function testFactoryClassLoads(): void
    {
        $factory = new Factory();

        $this->assertInstanceOf(Factory::class, $factory);
    }
}
