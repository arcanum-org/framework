<?php

declare(strict_types=1);

namespace Arcanum\Test\Flow\Conveyor\Middleware;

use Arcanum\Flow\Conveyor\Middleware\TransportGuard;
use Arcanum\Glitch\HttpException;
use Arcanum\Hyper\Attribute\HttpOnly;
use Arcanum\Hyper\StatusCode;
use Arcanum\Ignition\Transport;
use Arcanum\Rune\Attribute\CliOnly;
use Arcanum\Test\Fixture\Rune\CliOnlyFixture;
use Arcanum\Test\Fixture\Rune\HttpOnlyFixture;
use Arcanum\Test\Fixture\Rune\UnrestrictedFixture;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(TransportGuard::class)]
#[UsesClass(CliOnly::class)]
#[UsesClass(HttpOnly::class)]
#[UsesClass(HttpException::class)]
#[UsesClass(StatusCode::class)]
#[UsesClass(\Arcanum\Hyper\Phrase::class)]
#[UsesClass(Transport::class)]
final class TransportGuardTest extends TestCase
{
    // ---------------------------------------------------------------
    // HTTP transport
    // ---------------------------------------------------------------

    public function testHttpTransportAllowsUnrestrictedDto(): void
    {
        // Arrange
        $guard = new TransportGuard(Transport::Http);
        $dto = new UnrestrictedFixture();
        $called = false;

        // Act
        $guard($dto, function () use (&$called) {
            $called = true;
        });

        // Assert
        $this->assertTrue($called);
    }

    public function testHttpTransportAllowsHttpOnlyDto(): void
    {
        // Arrange
        $guard = new TransportGuard(Transport::Http);
        $dto = new HttpOnlyFixture();
        $called = false;

        // Act
        $guard($dto, function () use (&$called) {
            $called = true;
        });

        // Assert
        $this->assertTrue($called);
    }

    public function testHttpTransportRejectsCliOnlyDto(): void
    {
        // Arrange
        $guard = new TransportGuard(Transport::Http);
        $dto = new CliOnlyFixture();

        // Act & Assert
        $this->expectException(HttpException::class);
        $this->expectExceptionCode(StatusCode::MethodNotAllowed->value);
        $guard($dto, function () {
        });
    }

    public function testHttpRejectionMessageIncludesClassName(): void
    {
        // Arrange
        $guard = new TransportGuard(Transport::Http);
        $dto = new CliOnlyFixture();

        // Act
        try {
            $guard($dto, function () {
            });
            $this->fail('Expected HttpException');
        } catch (HttpException $e) {
            // Assert
            $this->assertStringContainsString('CliOnlyFixture', $e->getMessage());
            $this->assertStringContainsString('CLI-only', $e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    // CLI transport
    // ---------------------------------------------------------------

    public function testCliTransportAllowsUnrestrictedDto(): void
    {
        // Arrange
        $guard = new TransportGuard(Transport::Cli);
        $dto = new UnrestrictedFixture();
        $called = false;

        // Act
        $guard($dto, function () use (&$called) {
            $called = true;
        });

        // Assert
        $this->assertTrue($called);
    }

    public function testCliTransportAllowsCliOnlyDto(): void
    {
        // Arrange
        $guard = new TransportGuard(Transport::Cli);
        $dto = new CliOnlyFixture();
        $called = false;

        // Act
        $guard($dto, function () use (&$called) {
            $called = true;
        });

        // Assert
        $this->assertTrue($called);
    }

    public function testCliTransportRejectsHttpOnlyDto(): void
    {
        // Arrange
        $guard = new TransportGuard(Transport::Cli);
        $dto = new HttpOnlyFixture();

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTTP-only');
        $guard($dto, function () {
        });
    }

    public function testCliRejectionMessageIncludesClassName(): void
    {
        // Arrange
        $guard = new TransportGuard(Transport::Cli);
        $dto = new HttpOnlyFixture();

        // Act
        try {
            $guard($dto, function () {
            });
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            // Assert
            $this->assertStringContainsString('HttpOnlyFixture', $e->getMessage());
        }
    }

    // ---------------------------------------------------------------
    // Non-existent DTO class (handler-only routes)
    // ---------------------------------------------------------------

    public function testSkipsCheckForNonExistentDtoClass(): void
    {
        // Arrange — HandlerProxy with a class that doesn't exist
        $guard = new TransportGuard(Transport::Http);
        $proxy = new \Arcanum\Flow\Conveyor\Command('NonExistent\\Class', []);
        $called = false;

        // Act
        $guard($proxy, function () use (&$called) {
            $called = true;
        });

        // Assert
        $this->assertTrue($called);
    }
}
