<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition\Bootstrap;

use Arcanum\Cabinet\Container;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrap\Sessions;
use Arcanum\Ignition\HyperKernel;
use Arcanum\Ignition\Kernel;
use Arcanum\Ignition\RuneKernel;
use Arcanum\Session\CacheSessionDriver;
use Arcanum\Session\CookieSessionDriver;
use Arcanum\Session\SessionConfig;
use Arcanum\Session\SessionDriver;
use Arcanum\Session\ActiveSession;
use Arcanum\Toolkit\Encryption\EncryptionKey;
use Arcanum\Toolkit\Encryption\Encryptor;
use Arcanum\Toolkit\Encryption\SodiumEncryptor;
use Arcanum\Vault\ArrayDriver;
use Arcanum\Vault\CacheManager;
use Arcanum\Vault\KeyValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(Sessions::class)]
#[UsesClass(Container::class)]
#[UsesClass(\Arcanum\Cabinet\SimpleProvider::class)]
#[UsesClass(\Arcanum\Cabinet\PrototypeProvider::class)]
#[UsesClass(\Arcanum\Codex\Resolver::class)]
#[UsesClass(\Arcanum\Codex\Event\ClassRequested::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
#[UsesClass(SessionConfig::class)]
#[UsesClass(ActiveSession::class)]
#[UsesClass(CacheSessionDriver::class)]
#[UsesClass(\Arcanum\Vault\FileDriver::class)]
#[UsesClass(\Arcanum\Vault\InvalidArgument::class)]
#[UsesClass(CookieSessionDriver::class)]
#[UsesClass(ArrayDriver::class)]
#[UsesClass(KeyValidator::class)]
#[UsesClass(CacheManager::class)]
#[UsesClass(SodiumEncryptor::class)]
#[UsesClass(EncryptionKey::class)]
#[UsesClass(\Arcanum\Parchment\FileSystem::class)]
#[UsesClass(\Arcanum\Parchment\Reader::class)]
#[UsesClass(\Arcanum\Parchment\Writer::class)]
final class SessionsTest extends TestCase
{
    /**
     * @param array<string, mixed> $sessionConfig
     */
    private function buildContainer(array $sessionConfig = [], string $driver = 'file'): Container
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);

        $kernel = $this->createStub(HyperKernel::class);
        $kernel->method('filesDirectory')->willReturn(sys_get_temp_dir() . '/arcanum_session_test');
        $container->instance(Kernel::class, $kernel);

        $config = new Configuration([
            'session' => array_merge(['driver' => $driver], $sessionConfig),
        ]);
        $container->instance(Configuration::class, $config);

        return $container;
    }

    protected function tearDown(): void
    {
        $dir = sys_get_temp_dir() . '/arcanum_session_test/sessions';
        if (is_dir($dir)) {
            $files = glob($dir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    unlink($file);
                }
            }
            rmdir($dir);
        }

        $parent = sys_get_temp_dir() . '/arcanum_session_test';
        if (is_dir($parent)) {
            @rmdir($parent);
        }
    }

    public function testRegistersSessionConfigInContainer(): void
    {
        $container = $this->buildContainer(['cookie' => 'my_sess', 'lifetime' => 1800]);

        (new Sessions())->bootstrap($container);

        $config = $container->get(SessionConfig::class);
        $this->assertInstanceOf(SessionConfig::class, $config);
        $this->assertSame('my_sess', $config->cookieName);
        $this->assertSame(1800, $config->lifetime);
    }

    public function testRegistersActiveSession(): void
    {
        $container = $this->buildContainer();

        (new Sessions())->bootstrap($container);

        $this->assertInstanceOf(ActiveSession::class, $container->get(ActiveSession::class));
    }

    public function testRegistersFileDriverByDefault(): void
    {
        $container = $this->buildContainer();

        (new Sessions())->bootstrap($container);

        $this->assertInstanceOf(CacheSessionDriver::class, $container->get(SessionDriver::class));
    }

    public function testRegistersCacheDriver(): void
    {
        $container = $this->buildContainer([], 'cache');

        // CacheSessionDriver needs a CacheManager in the container.
        $manager = new CacheManager(
            defaultStore: 'array',
            stores: ['array' => ['driver' => 'array']],
        );
        $container->instance(CacheManager::class, $manager);

        (new Sessions())->bootstrap($container);

        $this->assertInstanceOf(CacheSessionDriver::class, $container->get(SessionDriver::class));
    }

    public function testRegistersCookieDriver(): void
    {
        $container = $this->buildContainer([], 'cookie');

        $encryptor = new SodiumEncryptor(new EncryptionKey(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
        $container->instance(Encryptor::class, $encryptor);

        (new Sessions())->bootstrap($container);

        $this->assertInstanceOf(CookieSessionDriver::class, $container->get(SessionDriver::class));
    }

    public function testSkipsCliKernel(): void
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);

        $kernel = $this->createStub(RuneKernel::class);
        $container->instance(Kernel::class, $kernel);

        $config = new Configuration(['session' => ['driver' => 'file']]);
        $container->instance(Configuration::class, $config);

        (new Sessions())->bootstrap($container);

        $this->assertFalse($container->has(SessionDriver::class));
    }

    public function testUnknownDriverThrows(): void
    {
        $container = $this->buildContainer([], 'memcached');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown session driver "memcached"');

        (new Sessions())->bootstrap($container);
    }
}
