<?php

declare(strict_types=1);

namespace Arcanum\Test\Ignition\Bootstrap;

use Arcanum\Cabinet\Container;
use Arcanum\Gather\Configuration as GatherConfiguration;
use Arcanum\Ignition\Bootstrap\Configuration;
use Arcanum\Ignition\ConfigurationCache;
use Arcanum\Ignition\Kernel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Configuration::class)]
#[UsesClass(Container::class)]
#[UsesClass(\Arcanum\Cabinet\SimpleProvider::class)]
#[UsesClass(\Arcanum\Cabinet\PrototypeProvider::class)]
#[UsesClass(\Arcanum\Codex\Resolver::class)]
#[UsesClass(\Arcanum\Codex\Event\ClassRequested::class)]
#[UsesClass(GatherConfiguration::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
#[UsesClass(ConfigurationCache::class)]
final class ConfigurationTest extends TestCase
{
    private string $tempDir;
    private string $configDir;
    private string $filesDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/arcanum_config_test_' . uniqid();
        $this->configDir = $this->tempDir . '/config';
        $this->filesDir = $this->tempDir . '/files';
        mkdir($this->configDir, 0777, true);
        mkdir($this->filesDir . '/cache', 0777, true);
    }

    protected function tearDown(): void
    {
        // Recursively delete temp dir
        $this->deleteDir($this->tempDir);
    }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function buildContainer(): Container
    {
        $container = new Container();
        $container->instance(\Arcanum\Cabinet\Application::class, $container);

        $kernel = $this->createStub(Kernel::class);
        $kernel->method('configDirectory')->willReturn($this->configDir);
        $kernel->method('filesDirectory')->willReturn($this->filesDir);
        $container->instance(Kernel::class, $kernel);

        return $container;
    }

    public function testRegistersConfigurationInstance(): void
    {
        // Arrange
        $container = $this->buildContainer();
        $bootstrapper = new Configuration();

        // Act
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(GatherConfiguration::class));
        $this->assertInstanceOf(GatherConfiguration::class, $container->get(GatherConfiguration::class));
    }

    public function testRegistersConfigurationCacheInstance(): void
    {
        // Arrange
        $container = $this->buildContainer();
        $bootstrapper = new Configuration();

        // Act
        $bootstrapper->bootstrap($container);

        // Assert
        $this->assertTrue($container->has(ConfigurationCache::class));
        $this->assertInstanceOf(ConfigurationCache::class, $container->get(ConfigurationCache::class));
    }

    public function testLoadsConfigFilesFromDirectory(): void
    {
        // Arrange
        file_put_contents($this->configDir . '/app.php', "<?php return ['name' => 'Arcanum'];\n");
        file_put_contents($this->configDir . '/log.php', "<?php return ['level' => 'debug'];\n");

        $container = $this->buildContainer();
        $bootstrapper = new Configuration();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var GatherConfiguration $config */
        $config = $container->get(GatherConfiguration::class);

        // Assert
        $this->assertSame('Arcanum', $config->get('app.name'));
        $this->assertSame('debug', $config->get('log.level'));
    }

    public function testConfigFileNameBecomesTopLevelKey(): void
    {
        // Arrange
        $dbConfig = "<?php return ['host' => 'localhost', 'port' => 3306];\n";
        file_put_contents($this->configDir . '/database.php', $dbConfig);

        $container = $this->buildContainer();
        $bootstrapper = new Configuration();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var GatherConfiguration $config */
        $config = $container->get(GatherConfiguration::class);

        // Assert
        $this->assertSame('localhost', $config->get('database.host'));
        $this->assertSame(3306, $config->get('database.port'));
    }

    public function testEmptyConfigDirectoryProducesEmptyConfiguration(): void
    {
        // Arrange — config dir exists but has no files
        $container = $this->buildContainer();
        $bootstrapper = new Configuration();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var GatherConfiguration $config */
        $config = $container->get(GatherConfiguration::class);

        // Assert
        $this->assertSame(0, $config->count());
    }

    public function testUsesConfigCacheWhenAvailable(): void
    {
        // Arrange — write a cache file, and put different data in config dir
        $cachePath = $this->filesDir . '/cache/config.php';
        file_put_contents($cachePath, "<?php return ['app' => ['name' => 'Cached']];\n");
        file_put_contents($this->configDir . '/app.php', "<?php return ['name' => 'FromFile'];\n");

        $container = $this->buildContainer();
        $bootstrapper = new Configuration();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var GatherConfiguration $config */
        $config = $container->get(GatherConfiguration::class);

        // Assert — should use cached value, not the file
        $this->assertSame('Cached', $config->get('app.name'));
    }

    public function testBypassesConfigCacheWhenFrameworkCacheDisabled(): void
    {
        // Arrange — both a cache file AND the live config files exist, but
        // cache.framework.enabled is false. The bootstrapper should skip
        // the cache and re-read from disk.
        $cachePath = $this->filesDir . '/cache/config.php';
        file_put_contents($cachePath, "<?php return ['app' => ['name' => 'Cached']];\n");
        file_put_contents($this->configDir . '/app.php', "<?php return ['name' => 'Fresh'];\n");
        file_put_contents(
            $this->configDir . '/cache.php',
            "<?php return ['framework' => ['enabled' => false]];\n",
        );

        $container = $this->buildContainer();
        $bootstrapper = new Configuration();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var GatherConfiguration $config */
        $config = $container->get(GatherConfiguration::class);

        // Assert — fresh value, not cached
        $this->assertSame('Fresh', $config->get('app.name'));
    }

    public function testHonoursConfigCacheWhenFrameworkCacheEnabled(): void
    {
        // Arrange — explicit cache.framework.enabled => true, cache present
        $cachePath = $this->filesDir . '/cache/config.php';
        file_put_contents($cachePath, "<?php return ['app' => ['name' => 'Cached']];\n");
        file_put_contents($this->configDir . '/app.php', "<?php return ['name' => 'Fresh'];\n");
        file_put_contents(
            $this->configDir . '/cache.php',
            "<?php return ['framework' => ['enabled' => true]];\n",
        );

        $container = $this->buildContainer();
        $bootstrapper = new Configuration();

        // Act
        $bootstrapper->bootstrap($container);

        /** @var GatherConfiguration $config */
        $config = $container->get(GatherConfiguration::class);

        // Assert — cache wins when bypass is off
        $this->assertSame('Cached', $config->get('app.name'));
    }

    public function testSkipsFileScanningWhenCacheExists(): void
    {
        // Arrange — cache exists, config dir has a file that would cause error if required
        $cachePath = $this->filesDir . '/cache/config.php';
        file_put_contents($cachePath, "<?php return ['cached' => true];\n");
        file_put_contents($this->configDir . '/bad.php', "<?php this is not valid php\n");

        $container = $this->buildContainer();
        $bootstrapper = new Configuration();

        // Act — should not throw because the bad file is never required
        $bootstrapper->bootstrap($container);

        /** @var GatherConfiguration $config */
        $config = $container->get(GatherConfiguration::class);

        // Assert
        $this->assertTrue($config->get('cached'));
    }
}
