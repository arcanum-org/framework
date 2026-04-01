<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition\Bootstrap;

use Arcanum\Cabinet\Container;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrap\Cache;
use Arcanum\Ignition\Kernel;
use Arcanum\Vault\ArrayDriver;
use Arcanum\Vault\CacheManager;
use Arcanum\Vault\InvalidArgument;
use Arcanum\Vault\KeyValidator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\SimpleCache\CacheInterface;

#[CoversClass(Cache::class)]
#[UsesClass(Container::class)]
#[UsesClass(\Arcanum\Cabinet\SimpleProvider::class)]
#[UsesClass(\Arcanum\Cabinet\PrototypeProvider::class)]
#[UsesClass(\Arcanum\Codex\Resolver::class)]
#[UsesClass(\Arcanum\Codex\Event\ClassRequested::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
#[UsesClass(CacheManager::class)]
#[UsesClass(ArrayDriver::class)]
#[UsesClass(KeyValidator::class)]
#[UsesClass(InvalidArgument::class)]
final class CacheTest extends TestCase
{
    /**
     * @param array<string, mixed> $cacheConfig
     */
    private function buildContainer(array $cacheConfig = []): Container
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);

        $kernel = $this->createStub(Kernel::class);
        $kernel->method('filesDirectory')->willReturn(sys_get_temp_dir());
        $container->instance(Kernel::class, $kernel);

        $config = new Configuration([
            'cache' => $cacheConfig,
        ]);
        $container->instance(Configuration::class, $config);

        return $container;
    }

    public function testRegistersCacheManagerInContainer(): void
    {
        $container = $this->buildContainer([
            'default' => 'array',
            'stores' => ['array' => ['driver' => 'array']],
        ]);

        (new Cache())->bootstrap($container);

        $this->assertTrue($container->has(CacheManager::class));
        $this->assertInstanceOf(CacheManager::class, $container->get(CacheManager::class));
    }

    public function testRegistersCacheInterfaceAsDefaultStore(): void
    {
        $container = $this->buildContainer([
            'default' => 'array',
            'stores' => ['array' => ['driver' => 'array']],
        ]);

        (new Cache())->bootstrap($container);

        $cache = $container->get(CacheInterface::class);
        $this->assertInstanceOf(ArrayDriver::class, $cache);
    }

    public function testDefaultStoreIsResolvable(): void
    {
        $container = $this->buildContainer([
            'default' => 'array',
            'stores' => ['array' => ['driver' => 'array']],
        ]);

        (new Cache())->bootstrap($container);

        /** @var CacheInterface $cache */
        $cache = $container->get(CacheInterface::class);
        $cache->set('test', 'value');
        $this->assertSame('value', $cache->get('test'));
    }

    public function testFallsBackToFileDriverWithNoConfig(): void
    {
        $container = $this->buildContainer([]);

        (new Cache())->bootstrap($container);

        $this->assertTrue($container->has(CacheManager::class));
        $this->assertTrue($container->has(CacheInterface::class));
    }
}
