<?php

declare(strict_types=1);

namespace Arcanum\Test\Testing;

use Arcanum\Testing\Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Factory::class)]
final class PackageSkeletonTest extends TestCase
{
    public function testFactoryClassLoads(): void
    {
        $factory = new Factory();

        $this->assertInstanceOf(Factory::class, $factory);
    }
}
