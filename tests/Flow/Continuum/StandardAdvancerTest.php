<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Continuum;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Flow\Continuum\StandardAdvancer;
use Arcanum\Flow\Interrupted;
use Arcanum\Test\Fixture\Counter;
use Arcanum\Test\Fixture\Concatenator;
use Arcanum\Test\Flow\Continuum\Fixture\BasicProgression;

#[CoversClass(StandardAdvancer::class)]
#[UsesClass(Interrupted::class)]
final class StandardAdvancerTest extends TestCase
{
    public function testStandardAdvancer(): void
    {
        // Arrange
        $advancer = new StandardAdvancer();
        $counter = new Counter();

        // Act
        $result = $advancer->advance(
            $counter,
            BasicProgression::fromClosure(function (Counter $counter, callable $next): void {
                $counter->increment();
                $next();
            }),
            BasicProgression::fromClosure(function (Counter $counter, callable $next): void {
                $counter->increment();
                $next();
            }),
        );

        // Assert
        $this->assertSame($counter, $result);
        $this->assertEquals(2, $counter->count());
    }

    public function testCallingNextExecutesSameOrder(): void
    {
        // Arrange
        $advancer = new StandardAdvancer();
        $concatenator = new Concatenator();

        // Act
        $result = $advancer->advance(
            $concatenator,
            BasicProgression::fromClosure(function (Concatenator $payload, callable $next): void {
                $payload->add('a');
                $next();
            }),
            BasicProgression::fromClosure(function (Concatenator $payload, callable $next): void {
                $payload->add('b');
                $next();
            }),
            BasicProgression::fromClosure(function (Concatenator $payload, callable $next): void {
                $payload->add('c');
                $next();
            }),
        );

        // Assert
        $this->assertSame($concatenator, $result);
        $this->assertEquals('abc', (string)$concatenator);
    }

    public function testNotNotCallingNextInterruptsFlow(): void
    {
        // Arrange
        $advancer = new StandardAdvancer();
        $concatenator = new Concatenator();

        // Act

        $result = null;
        try {
            $result = $advancer->advance(
                $concatenator,
                BasicProgression::fromClosure(function (Concatenator $payload, callable $next): void {
                    $payload->add('a');
                    $next();
                }),
                BasicProgression::fromClosure(function (Concatenator $payload, callable $next): void {
                    $payload->add('b');
                    $next();
                }),
                BasicProgression::fromClosure(function (Concatenator $payload, callable $next): void {
                    $payload->add('c');
                }),
                BasicProgression::fromClosure(function (Concatenator $payload, callable $next): void {
                    $payload->add('d');
                    $next();
                }),
            );
        } catch (Interrupted $e) {
            // Assert
            $this->assertSame(null, $result);

            // never added "d"
            $this->assertEquals('abc', (string)$concatenator);
        }
    }
}
