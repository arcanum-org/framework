<?php

declare(strict_types=1);

namespace Arcanum\Test\Session;

use Arcanum\Session\SessionConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SessionConfig::class)]
final class SessionConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new SessionConfig();

        $this->assertSame('arcanum_session', $config->cookieName);
        $this->assertSame(7200, $config->lifetime);
        $this->assertSame('/', $config->path);
        $this->assertSame('', $config->domain);
        $this->assertTrue($config->secure);
        $this->assertTrue($config->httpOnly);
        $this->assertSame('Lax', $config->sameSite);
    }

    public function testCookieHeaderContainsAllAttributes(): void
    {
        $config = new SessionConfig(
            cookieName: 'my_session',
            lifetime: 3600,
            path: '/app',
            domain: 'example.com',
            secure: true,
            httpOnly: true,
            sameSite: 'Strict',
        );

        $header = $config->cookieHeader('abc123');

        $this->assertStringContainsString('my_session=abc123', $header);
        $this->assertStringContainsString('Max-Age=3600', $header);
        $this->assertStringContainsString('Path=/app', $header);
        $this->assertStringContainsString('Domain=example.com', $header);
        $this->assertStringContainsString('Secure', $header);
        $this->assertStringContainsString('HttpOnly', $header);
        $this->assertStringContainsString('SameSite=Strict', $header);
    }

    public function testCookieHeaderOmitsDomainWhenEmpty(): void
    {
        $config = new SessionConfig(domain: '');

        $header = $config->cookieHeader('abc');

        $this->assertStringNotContainsString('Domain=', $header);
    }

    public function testCookieHeaderOmitsSecureWhenFalse(): void
    {
        $config = new SessionConfig(secure: false);

        $header = $config->cookieHeader('abc');

        $this->assertStringNotContainsString('Secure', $header);
    }

    public function testCookieHeaderExpireMode(): void
    {
        $config = new SessionConfig();

        $header = $config->cookieHeader('abc', expire: true);

        $this->assertStringContainsString('arcanum_session=;', $header);
        $this->assertStringContainsString('Max-Age=0', $header);
        $this->assertStringContainsString('Expires=Thu, 01 Jan 1970', $header);
    }
}
