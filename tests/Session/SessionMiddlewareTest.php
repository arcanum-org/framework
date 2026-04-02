<?php

declare(strict_types=1);

namespace Arcanum\Test\Session;

use Arcanum\Session\CookieSessionDriver;
use Arcanum\Session\CsrfToken;
use Arcanum\Session\Flash;
use Arcanum\Session\Session;
use Arcanum\Session\SessionConfig;
use Arcanum\Session\SessionDriver;
use Arcanum\Session\SessionId;
use Arcanum\Session\SessionMiddleware;
use Arcanum\Session\ActiveSession;
use Arcanum\Toolkit\Encryption\EncryptionKey;
use Arcanum\Toolkit\Encryption\SodiumEncryptor;
use Arcanum\Toolkit\Random;
use Arcanum\Vault\ArrayDriver;
use Arcanum\Session\CacheSessionDriver;
use Arcanum\Vault\KeyValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

#[CoversClass(SessionMiddleware::class)]
#[UsesClass(Session::class)]
#[UsesClass(SessionId::class)]
#[UsesClass(SessionConfig::class)]
#[UsesClass(ActiveSession::class)]
#[UsesClass(CsrfToken::class)]
#[UsesClass(Flash::class)]
#[UsesClass(Random::class)]
#[UsesClass(CacheSessionDriver::class)]
#[UsesClass(ArrayDriver::class)]
#[UsesClass(KeyValidator::class)]
final class SessionMiddlewareTest extends TestCase
{
    /**
     * @param array<string, string> $cookies
     */
    private function stubRequest(array $cookies = []): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getCookieParams')->willReturn($cookies);
        return $request;
    }

    private function stubHandler(ResponseInterface $response): RequestHandlerInterface
    {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($response);
        return $handler;
    }

    private function stubResponse(): ResponseInterface
    {
        $response = $this->createStub(ResponseInterface::class);
        $response->method('withAddedHeader')->willReturnSelf();
        return $response;
    }

    public function testCreatesNewSessionForFirstVisit(): void
    {
        $driver = new CacheSessionDriver(new ArrayDriver());
        $config = new SessionConfig();
        $registry = new ActiveSession();
        $middleware = new SessionMiddleware($driver, $config, $registry);

        $middleware->process(
            $this->stubRequest(),
            $this->stubHandler($this->stubResponse()),
        );

        $this->assertTrue($registry->has());
        $this->assertSame('', $registry->get()->identityId());
    }

    public function testRestoresExistingSession(): void
    {
        $cache = new ArrayDriver();
        $driver = new CacheSessionDriver($cache);
        $config = new SessionConfig();
        $registry = new ActiveSession();

        // Pre-populate a session.
        $sessionId = SessionId::generate();
        $data = ['_identity' => 'user-5', '_csrf' => str_repeat('ab', 32), '_flash' => []];
        $driver->write($sessionId->value, $data, 3600);

        $middleware = new SessionMiddleware($driver, $config, $registry);

        $middleware->process(
            $this->stubRequest(['arcanum_session' => $sessionId->value]),
            $this->stubHandler($this->stubResponse()),
        );

        $this->assertSame('user-5', $registry->get()->identityId());
    }

    public function testRejectsInvalidSessionIdFormat(): void
    {
        $driver = new CacheSessionDriver(new ArrayDriver());
        $config = new SessionConfig();
        $registry = new ActiveSession();
        $middleware = new SessionMiddleware($driver, $config, $registry);

        $middleware->process(
            $this->stubRequest(['arcanum_session' => 'not-a-valid-hex-id']),
            $this->stubHandler($this->stubResponse()),
        );

        // Should create a new session, not crash.
        $this->assertTrue($registry->has());
        $this->assertSame('', $registry->get()->identityId());
    }

    public function testSetsSessionCookieOnResponse(): void
    {
        $driver = new CacheSessionDriver(new ArrayDriver());
        $config = new SessionConfig(cookieName: 'test_sess');
        $registry = new ActiveSession();
        $middleware = new SessionMiddleware($driver, $config, $registry);

        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('withAddedHeader')
            ->with('Set-Cookie', $this->stringContains('test_sess='))
            ->willReturnSelf();

        $middleware->process(
            $this->stubRequest(),
            $this->stubHandler($response),
        );
    }

    public function testRegenerationDestroysOldSessionInDriver(): void
    {
        $cache = new ArrayDriver();
        $driver = new CacheSessionDriver($cache);
        $config = new SessionConfig();
        $registry = new ActiveSession();

        $oldId = SessionId::generate();
        $driver->write($oldId->value, ['_identity' => 'user-1', '_csrf' => str_repeat('ab', 32), '_flash' => []], 3600);

        $middleware = new SessionMiddleware($driver, $config, $registry);

        // The handler triggers regeneration by setting identity.
        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function () use ($registry): ResponseInterface {
            $registry->get()->setIdentity('user-1');
            $response = $this->createStub(ResponseInterface::class);
            $response->method('withAddedHeader')->willReturnSelf();
            return $response;
        });

        $middleware->process(
            $this->stubRequest(['arcanum_session' => $oldId->value]),
            $handler,
        );

        // Old session should be destroyed.
        $this->assertSame([], $driver->read($oldId->value));
    }

    public function testInvalidationDestroysOldSession(): void
    {
        $cache = new ArrayDriver();
        $driver = new CacheSessionDriver($cache);
        $config = new SessionConfig();
        $registry = new ActiveSession();

        $oldId = SessionId::generate();
        $driver->write($oldId->value, ['_identity' => 'user-1', '_csrf' => str_repeat('ab', 32), '_flash' => []], 3600);

        $middleware = new SessionMiddleware($driver, $config, $registry);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function () use ($registry): ResponseInterface {
            $registry->get()->invalidate();
            $response = $this->createStub(ResponseInterface::class);
            $response->method('withAddedHeader')->willReturnSelf();
            return $response;
        });

        $middleware->process(
            $this->stubRequest(['arcanum_session' => $oldId->value]),
            $handler,
        );

        $this->assertSame([], $driver->read($oldId->value));
    }

    public function testPersistsSessionDataAfterRequest(): void
    {
        $cache = new ArrayDriver();
        $driver = new CacheSessionDriver($cache);
        $config = new SessionConfig();
        $registry = new ActiveSession();
        $middleware = new SessionMiddleware($driver, $config, $registry);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturnCallback(function () use ($registry): ResponseInterface {
            $registry->get()->flash()->set('msg', 'Hello');
            $response = $this->createStub(ResponseInterface::class);
            $response->method('withAddedHeader')->willReturnSelf();
            return $response;
        });

        $middleware->process($this->stubRequest(), $handler);

        // Read the persisted data.
        $sessionId = $registry->get()->id()->value;
        $data = $driver->read($sessionId);

        $this->assertSame(['msg' => 'Hello'], $data['_flash']);
    }
}
