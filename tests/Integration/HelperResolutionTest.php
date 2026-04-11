<?php

declare(strict_types=1);

namespace Arcanum\Test\Integration;

use Arcanum\Cabinet\Application;
use Arcanum\Gather\Configuration;
use Arcanum\Ignition\Bootstrap\Helpers;
use Arcanum\Ignition\Bootstrap\Routing;
use Arcanum\Hyper\JsonResponseRenderer;
use Arcanum\Shodo\HelperResolver;
use Arcanum\Shodo\Helpers\RouteHelper;
use Arcanum\Testing\TestKernel;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

/**
 * Integration tests for template helper resolution through Bootstrap\Routing.
 *
 * Verifies that bootstrapping wires HelperResolver correctly:
 * global framework helpers are present, domain-scoped helpers from
 * Helpers.php files are discovered, and the resolver returns the
 * right set for each DTO class.
 */
#[CoversNothing]
final class HelperResolutionTest extends TestCase
{
    private string $rootDir;

    protected function setUp(): void
    {
        $this->rootDir = sys_get_temp_dir() . '/arcanum_helper_int_' . uniqid();
        mkdir($this->rootDir . '/app/Domain', 0755, true);
        mkdir($this->rootDir . '/app/Pages', 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->rootDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function bootstrap(): Application
    {
        // TestKernel gives us the same shared bindings any future app
        // handler test will inherit (FrozenClock, ArrayDriver,
        // ActiveIdentity) — this test doesn't need them, but using
        // TestKernel proves it doesn't get in the way of bootstrapper-
        // driven setup either.
        $kernel = new TestKernel(rootDirectory: $this->rootDir);
        $container = $kernel->container();
        $container->instance(Application::class, $container);
        $container->instance(\Psr\Container\ContainerInterface::class, $container);

        $config = new Configuration([
            'app' => [
                'namespace' => 'App\\Domain',
                'pages_namespace' => 'App\\Pages',
                'pages_directory' => $this->rootDir . '/app/Pages',
                'url' => 'https://example.com',
            ],
            'routes' => [],
            'formats' => [
                'default' => 'json',
                'formats' => [
                    'json' => [
                        'content_type' => 'application/json',
                        'renderer' => JsonResponseRenderer::class,
                    ],
                ],
            ],
        ]);
        $container->instance(Configuration::class, $config);

        // Kernel::class is already bound — TestKernel auto-binds itself.
        (new Routing())->bootstrap($container);
        (new Helpers())->bootstrap($container);

        return $container;
    }

    public function testHelperResolverIsRegistered(): void
    {
        // Arrange & Act
        $container = $this->bootstrap();

        // Assert
        $this->assertTrue($container->has(HelperResolver::class));
    }

    public function testGlobalFrameworkHelpersAvailableForAnyDto(): void
    {
        // Arrange
        $container = $this->bootstrap();

        /** @var HelperResolver $resolver */
        $resolver = $container->get(HelperResolver::class);

        // Act
        $helpers = $resolver->for('App\\Domain\\Query\\Health');

        // Assert — Format, Str, Arr always present; Route present because UrlResolver exists
        $this->assertArrayHasKey('Format', $helpers);
        $this->assertArrayHasKey('Str', $helpers);
        $this->assertArrayHasKey('Arr', $helpers);
        $this->assertArrayHasKey('Route', $helpers);
    }

    public function testRouteHelperGeneratesUrls(): void
    {
        // Arrange
        $container = $this->bootstrap();

        /** @var HelperResolver $resolver */
        $resolver = $container->get(HelperResolver::class);
        $helpers = $resolver->for('App\\Domain\\Query\\Health');

        // Act
        $route = $helpers['Route'];
        $this->assertInstanceOf(RouteHelper::class, $route);
        $url = $route->url('App\\Domain\\Query\\Health');

        // Assert
        $this->assertSame('https://example.com/health', $url);
    }

    public function testAppHelpersFileRegistersGlobalAliases(): void
    {
        // Arrange — app/Helpers/Helpers.php registers an app-wide alias
        // that should be available to every DTO, not just one domain.
        mkdir($this->rootDir . '/app/Helpers', 0755, true);
        file_put_contents(
            $this->rootDir . '/app/Helpers/Helpers.php',
            "<?php\nreturn ['App' => \\stdClass::class];",
        );

        $container = $this->bootstrap();

        /** @var HelperResolver $resolver */
        $resolver = $container->get(HelperResolver::class);

        // Act — resolve helpers for two unrelated DTOs
        $shopHelpers = $resolver->for('App\\Domain\\Shop\\Query\\Products');
        $authHelpers = $resolver->for('App\\Pages\\Login');

        // Assert — App alias is present everywhere as a global helper
        $this->assertArrayHasKey('App', $shopHelpers);
        $this->assertArrayHasKey('App', $authHelpers);
    }

    public function testDomainHelpersOverrideAppGlobals(): void
    {
        // Arrange — both an app-wide global and a domain-specific override
        // exist for the same alias. The domain version should win.
        mkdir($this->rootDir . '/app/Helpers', 0755, true);
        file_put_contents(
            $this->rootDir . '/app/Helpers/Helpers.php',
            "<?php\nreturn ['Tag' => \\stdClass::class];",
        );

        mkdir($this->rootDir . '/app/Domain/Shop', 0755, true);
        file_put_contents(
            $this->rootDir . '/app/Domain/Shop/Helpers.php',
            "<?php\nreturn ['Tag' => \\ArrayObject::class];",
        );

        $container = $this->bootstrap();

        /** @var HelperResolver $resolver */
        $resolver = $container->get(HelperResolver::class);

        // Act
        $shopHelpers = $resolver->for('App\\Domain\\Shop\\Query\\Products');
        $otherHelpers = $resolver->for('App\\Pages\\Login');

        // Assert — Shop sees the domain-specific override; everything else
        // sees the app-wide global.
        $this->assertInstanceOf(\ArrayObject::class, $shopHelpers['Tag']);
        $this->assertInstanceOf(\stdClass::class, $otherHelpers['Tag']);
    }

    public function testDomainScopedHelpersDiscovered(): void
    {
        // Arrange — create a Helpers.php in the Shop domain
        mkdir($this->rootDir . '/app/Domain/Shop', 0755, true);
        file_put_contents(
            $this->rootDir . '/app/Domain/Shop/Helpers.php',
            "<?php\nreturn ['Cart' => \\stdClass::class];",
        );

        $container = $this->bootstrap();

        /** @var HelperResolver $resolver */
        $resolver = $container->get(HelperResolver::class);

        // Act
        $shopHelpers = $resolver->for('App\\Domain\\Shop\\Query\\Products');
        $authHelpers = $resolver->for('App\\Domain\\Auth\\Query\\Whoami');

        // Assert — Cart only available in Shop domain
        $this->assertArrayHasKey('Cart', $shopHelpers);
        $this->assertArrayNotHasKey('Cart', $authHelpers);

        // Global helpers still present in both
        $this->assertArrayHasKey('Format', $shopHelpers);
        $this->assertArrayHasKey('Format', $authHelpers);
    }
}
