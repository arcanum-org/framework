<?php

declare(strict_types=1);

namespace Arcanum\Test\Integration;

use Arcanum\Cabinet\Application;
use Arcanum\Cabinet\Container;
use Arcanum\Flow\Conveyor\Bus;
use Arcanum\Hyper\EmptyResponseRenderer;
use Arcanum\Hyper\Server;
use Arcanum\Hyper\ServerAdapter;
use Arcanum\Ignition\HyperKernel;
use Arcanum\Ignition\Kernel;
use Arcanum\Ignition\RuneKernel;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Verifies that a minimal from-scratch bootstrap produces a working container.
 *
 * The goal: a bootstrap file with only container + kernel binding + directory
 * specs should work — Bus, EventDispatcher, ServerAdapter, and container
 * interfaces are all auto-registered by the framework.
 */
#[CoversNothing]
final class BootstrapSelfWiringTest extends TestCase
{
    private string $tempDir;
    private mixed $originalAppKey;

    protected function setUp(): void
    {
        $this->originalAppKey = $_ENV['APP_KEY'] ?? null;

        $this->tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'arcanum_test_' . bin2hex(random_bytes(4));
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'config', 0755, true);
        mkdir($this->tempDir . DIRECTORY_SEPARATOR . 'files' . DIRECTORY_SEPARATOR . 'cache', 0755, true);

        file_put_contents(
            $this->tempDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'app.php',
            "<?php\nreturn ['namespace' => 'App', 'name' => 'Test', 'debug' => true, "
            . "'pages_namespace' => 'App\\\\Pages'];\n",
        );

        file_put_contents(
            $this->tempDir . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'log.php',
            "<?php\nreturn ['handlers' => [], 'channels' => []];\n",
        );

        // Security bootstrapper requires APP_KEY.
        $_ENV['APP_KEY'] = 'base64:' . base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES));
    }

    protected function tearDown(): void
    {
        if ($this->originalAppKey === null) {
            unset($_ENV['APP_KEY']);
            putenv('APP_KEY');
        } else {
            $_ENV['APP_KEY'] = $this->originalAppKey;
        }

        $this->removeDirectory($this->tempDir);
    }

    #[RunInSeparateProcess]
    public function testHyperKernelAutoRegistersFrameworkServices(): void
    {
        $container = new Container();
        $kernel = new HyperKernel($this->tempDir);
        $container->instance(Kernel::class, $kernel);

        $kernel->bootstrap($container);

        $this->assertTrue($container->has(Application::class));
        $this->assertSame($container, $container->get(Application::class));

        $this->assertTrue($container->has(ContainerInterface::class));
        $this->assertSame($container, $container->get(ContainerInterface::class));

        $this->assertTrue($container->has(Bus::class));
        $this->assertInstanceOf(Bus::class, $container->get(Bus::class));

        $this->assertTrue($container->has(EventDispatcherInterface::class));
        $this->assertInstanceOf(EventDispatcherInterface::class, $container->get(EventDispatcherInterface::class));

        $this->assertTrue($container->has(ServerAdapter::class));
        $this->assertInstanceOf(ServerAdapter::class, $container->get(ServerAdapter::class));

        $this->assertTrue($container->has(Server::class));
        $this->assertInstanceOf(Server::class, $container->get(Server::class));

        $this->assertTrue($container->has(EmptyResponseRenderer::class));
        $this->assertInstanceOf(EmptyResponseRenderer::class, $container->get(EmptyResponseRenderer::class));
    }

    #[RunInSeparateProcess]
    public function testRuneKernelAutoRegistersFrameworkServices(): void
    {
        $_SERVER['argv'] = ['bin/arcanum', 'list'];

        $container = new Container();
        $kernel = new RuneKernel($this->tempDir);
        $container->instance(Kernel::class, $kernel);

        $kernel->bootstrap($container);

        $this->assertTrue($container->has(Application::class));
        $this->assertSame($container, $container->get(Application::class));

        $this->assertTrue($container->has(ContainerInterface::class));
        $this->assertSame($container, $container->get(ContainerInterface::class));

        $this->assertTrue($container->has(Bus::class));
        $this->assertInstanceOf(Bus::class, $container->get(Bus::class));

        $this->assertTrue($container->has(EventDispatcherInterface::class));
        $this->assertInstanceOf(EventDispatcherInterface::class, $container->get(EventDispatcherInterface::class));
    }

    #[RunInSeparateProcess]
    public function testAppCanOverrideBusBeforeBootstrap(): void
    {
        $customBus = $this->createStub(Bus::class);

        $container = new Container();
        $container->instance(Bus::class, $customBus);

        $kernel = new HyperKernel($this->tempDir);
        $container->instance(Kernel::class, $kernel);

        $kernel->bootstrap($container);

        $this->assertSame($customBus, $container->get(Bus::class));
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var \SplFileInfo $item */
        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
