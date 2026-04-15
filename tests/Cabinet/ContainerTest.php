<?php

declare(strict_types=1);

namespace Arcanum\Test\Cabinet;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Test\Fixture;
use Arcanum\Cabinet\CircularDependency;
use Arcanum\Cabinet\InvalidKey;
use Arcanum\Cabinet\Container;
use Arcanum\Cabinet\ServiceNotFound;
use Arcanum\Codex\Error\UnresolvableClass;
use Arcanum\Flow\Continuum\Collection;
use Arcanum\Flow\Pipeline\System;

#[CoversClass(Container::class)]
#[UsesClass(\Arcanum\Cabinet\SimpleProvider::class)]
#[UsesClass(\Arcanum\Cabinet\PrototypeProvider::class)]
#[UsesClass(UnresolvableClass::class)]
#[UsesClass(InvalidKey::class)]
#[UsesClass(ServiceNotFound::class)]
#[UsesClass(CircularDependency::class)]
#[UsesClass(\Arcanum\Codex\Resolver::class)]
#[UsesClass(\Arcanum\Codex\Event\ClassRequested::class)]
#[UsesClass(\Arcanum\Flow\Continuum\ContinuationCollection::class)]
#[UsesClass(\Arcanum\Flow\Pipeline\PipelayerSystem::class)]
final class ContainerTest extends TestCase
{
    public function testContainerImplementsArrayAccess(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        /** @var \Arcanum\Flow\Continuum\Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);

        $service = new Fixture\SimpleService(new Fixture\SimpleDependency());

        // Act
        $container[Fixture\SimpleService::class] = $service;

        // Assert
        $this->assertSame($service, $container[Fixture\SimpleService::class]);
    }

    public function testArrayAccessWithNullProviderForClassThatDoesExists(): void
    {
        // Arrange
        $service = new Fixture\SimpleService(new Fixture\SimpleDependency());

        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->once())
            ->method('resolve')
            ->with(Fixture\SimpleService::class)
            ->willReturn($service);

        /** @var \Arcanum\Flow\Continuum\Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);

        // Act
        $service = $container[Fixture\SimpleService::class];

        // Assert
        $this->assertInstanceOf(Fixture\SimpleService::class, $service);
    }

    public function testContainerThrowsIfArrayAccessOffsetDoesNotExist(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->never())
            ->method('send');

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('send');

        $system->expects($this->never())
            ->method('pipe');

        $container = new Container($resolver, $collection, $system);

        // Assert
        $this->expectException(ServiceNotFound::class);

        // Act
        $container[Fixture\DoesNotExist::class]; /** @phpstan-ignore-line */
    }

    public function testContainerOffsetUnset(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('send');

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('send');

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->never())
            ->method('pipeline');

        $system->expects($this->never())
            ->method('purge');

        $container = new Container($resolver, $collection, $system);
        $container[Fixture\SimpleService::class] = new Fixture\SimpleService(new Fixture\SimpleDependency());

        // Act
        unset($container[Fixture\SimpleService::class]);

        // Assert
        $this->assertFalse(isset($container[Fixture\SimpleService::class]));
    }

    public function testContainerOnlyAcceptsStringKeys(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->never())
            ->method('send');

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->never())
            ->method('pipeline');

        $system->expects($this->never())
            ->method('purge');

        $system->expects($this->never())
            ->method('send');

        $container = new Container($resolver, $collection, $system);

        // Assert
        $this->expectException(InvalidKey::class);

        // Act
        $container[0] = 'bar';
    }

    public function testContainerCannotAccessNonStringKey(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->never())
            ->method('send');

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->never())
            ->method('send');

        $container = new Container($resolver, $collection, $system);

        // Assert
        $this->expectException(InvalidKey::class);

        // Act
        $container[0];
    }

    public function testContainerGetService(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);
        $service = new Fixture\SimpleService(new Fixture\SimpleDependency());
        $container[Fixture\SimpleService::class] = $service;

        // Act
        $result = $container->get(Fixture\SimpleService::class);

        // Assert
        $this->assertSame($service, $result);
    }

    public function testContainerGetThrowsIfServiceDoesNotExist(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->never())
            ->method('send');

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->never())
            ->method('send');

        $container = new Container($resolver, $collection, $system);

        // Assert
        $this->expectException(ServiceNotFound::class);

        // Act
        $container->get(Fixture\DoesNotExist::class); /** @phpstan-ignore-line */
    }

    public function testContainerHas(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->never())
            ->method('send');

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->never())
            ->method('send');

        $container = new Container($resolver, $collection, $system);
        $container[Fixture\SimpleService::class] = new Fixture\SimpleService(new Fixture\SimpleDependency());

        // Act
        $result = $container->has(Fixture\SimpleService::class);

        // Assert
        $this->assertTrue($result);
    }

    public function testContainerProvider(): void
    {
        // Arrange

        $service = new Fixture\SimpleService(new Fixture\SimpleDependency());

        /** @var \Arcanum\Cabinet\Provider&\PHPUnit\Framework\MockObject\MockObject */
        $provider = $this->getMockBuilder(\Arcanum\Cabinet\Provider::class)
            ->onlyMethods(['__invoke'])
            ->getMock();

        $provider->expects($this->once())
            ->method('__invoke')
            ->willReturn($service);

        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);

        // Act
        $container->provider(Fixture\SimpleService::class, $provider);
        $result = $container->get(Fixture\SimpleService::class);

        // Assert
        $this->assertSame($service, $result);
    }

    public function testContainerProviderWithStringProvider(): void
    {
        // Arrange
        $service = new Fixture\SimpleService(new Fixture\SimpleDependency());

        /** @var \Arcanum\Cabinet\Provider&\PHPUnit\Framework\MockObject\MockObject */
        $provider = $this->getMockBuilder(\Arcanum\Cabinet\Provider::class)
            ->onlyMethods(['__invoke'])
            ->getMock();

        $provider->expects($this->once())
            ->method('__invoke')
            ->willReturn($service);

        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->once())
            ->method('resolve')
            ->with(\Arcanum\Cabinet\Provider::class)
            ->willReturn($provider);

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);

        // Act
        $container->provider(Fixture\SimpleService::class, \Arcanum\Cabinet\Provider::class);
        $result = $container->get(Fixture\SimpleService::class);

        // Assert
        $this->assertSame($service, $result);
    }

    public function testContainerFactory(): void
    {
        // Arrange
        $service = new Fixture\SimpleService(new Fixture\SimpleDependency());

        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);

        // Act
        $container->factory(Fixture\SimpleService::class, fn() => $service);
        $result = $container->get(Fixture\SimpleService::class);

        // Assert
        $this->assertSame($service, $result);
    }

    public function testContainerService(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->once())
            ->method('resolve')
            ->with(Fixture\SimpleService::class)
            ->willReturn(new Fixture\SimpleService(new Fixture\SimpleDependency()));

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);
        $container->service(Fixture\SimpleService::class);

        // Act
        $result = $container->get(Fixture\SimpleService::class);

        // Assert
        $this->assertInstanceOf(Fixture\SimpleService::class, $result);
        $this->assertSame($container->get(Fixture\SimpleService::class), $result);
    }

    public function testResolveDependencies(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->once())
            ->method('resolve')
            ->with(Fixture\SimpleService::class)
            ->willReturn(new Fixture\SimpleService(new Fixture\SimpleDependency()));

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);
        $container->service(Fixture\SimpleService::class);
        $container->service(Fixture\SimpleDependency::class);

        // Act
        $result = $container->get(Fixture\SimpleService::class);

        // Assert
        $this->assertInstanceOf(Fixture\SimpleService::class, $result);
    }

    public function testResolveDependenciesNotRegisteredButFindable(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->once())
            ->method('resolve')
            ->with(Fixture\SimpleService::class)
            ->willReturn(new Fixture\SimpleService(new Fixture\SimpleDependency()));

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);
        $container->service(Fixture\SimpleService::class);

        // Act
        $result = $container->get(Fixture\SimpleService::class);

        // Assert
        $this->assertInstanceOf(Fixture\SimpleService::class, $result);
    }

    public function testRegisterAnInstance(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);
        $instance = new Fixture\SimpleService(new Fixture\SimpleDependency());
        $container->instance(Fixture\SimpleService::class, $instance);

        // Act
        $result = $container->get(Fixture\SimpleService::class);

        // Assert
        $this->assertSame($instance, $result);
    }

    public function testPrototype(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->exactly(2))
            ->method('resolve')
            ->with(Fixture\SimpleService::class)
            ->willReturnCallback(function () {
                return new Fixture\SimpleService(new Fixture\SimpleDependency());
            });

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);
        $container->prototype(Fixture\SimpleService::class);

        // Act
        $result = $container->get(Fixture\SimpleService::class);

        // Assert
        $this->assertInstanceOf(Fixture\SimpleService::class, $result);
        $this->assertNotSame($container->get(Fixture\SimpleService::class), $result);
    }

    public function testPrototypeFactory(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);
        $container->prototypeFactory(
            serviceName: Fixture\SimpleService::class,
            factory: fn() => new Fixture\SimpleService(new Fixture\SimpleDependency())
        );

        // Act
        $result = $container->get(Fixture\SimpleService::class);

        // Assert
        $this->assertInstanceOf(Fixture\SimpleService::class, $result);
        $this->assertNotSame($container->get(Fixture\SimpleService::class), $result);
    }

    public function testRegisterConcreteImplementationOfInterface(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->once())
            ->method('resolve')
            ->with(Fixture\ConcreteService::class)
            ->willReturnCallback(fn() => new Fixture\ConcreteService());

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);
        $container->service(Fixture\ServiceInterface::class, Fixture\ConcreteService::class);

        // Act
        $result = $container->get(Fixture\ServiceInterface::class);

        // Assert
        $this->assertInstanceOf(Fixture\ConcreteService::class, $result);
    }

    public function testServiceThrowsIfImplementationStringIsNotAClassString(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->never())
            ->method('send');

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->never())
            ->method('send');

        $container = new Container($resolver, $collection, $system);

        $serviceName = Fixture\ServiceInterface::class;

        $implementation = 'string';

        // Assert
        $this->expectException(ServiceNotFound::class);
        $this->expectExceptionMessage("class 'string' does not exist");

        // Act
        $container->service($serviceName, $implementation); // @phpstan-ignore-line
    }

    public function testServiceWith(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith'])
            ->getMock();

        $resolver->expects($this->once())
            ->method('resolveWith')
            ->with(Fixture\ServiceWithInterface::class, [Fixture\ConcreteService::class])
            ->willReturn(new Fixture\ServiceWithInterface(new Fixture\ConcreteService()));

        $resolver->expects($this->never())
            ->method('resolve');

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('continuation');

        $collection->expects($this->never())
            ->method('add');

        $collection->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('pipe');

        $system->expects($this->once())
            ->method('send')
            ->willReturnCallback(fn(string $key, object $object) => $object);

        $container = new Container($resolver, $collection, $system);

        $container->serviceWith(
            serviceName: Fixture\ServiceWithInterface::class,
            dependencies: [Fixture\ConcreteService::class]
        );

        // Act
        $result = $container->get(Fixture\ServiceWithInterface::class);

        // Assert
        $this->assertInstanceOf(Fixture\ServiceWithInterface::class, $result);
        $this->assertInstanceOf(Fixture\ConcreteService::class, $result->dependency);
    }

    public function testContainerSpecify(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith', 'specify'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        $resolver->expects($this->once())
            ->method('specify')
            ->with(Fixture\SimpleService::class, Fixture\SimpleDependency::class, Fixture\SimpleDependency::class);

        /** @var \Arcanum\Flow\Continuum\Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('send');

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('send');

        $container = new Container($resolver, $collection, $system);

        // Act
        $container->specify(
            Fixture\SimpleService::class,
            Fixture\SimpleDependency::class,
            Fixture\SimpleDependency::class
        );
    }

    public function testContainerSpecifyWithArrayWhenParameter(): void
    {
        // Arrange
        /** @var \Arcanum\Codex\Resolver&\PHPUnit\Framework\MockObject\MockObject */
        $resolver = $this->getMockBuilder(\Arcanum\Codex\Resolver::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['resolve', 'resolveWith', 'specify'])
            ->getMock();

        $resolver->expects($this->never())
            ->method('resolveWith');

        $resolver->expects($this->never())
            ->method('resolve');

        $resolver->expects($this->once())
            ->method('specify')
            ->with(
                [Fixture\SimpleService::class, Fixture\SimpleClass::class],
                Fixture\SimpleDependency::class,
                Fixture\SimpleDependency::class
            );

        /** @var Collection&\PHPUnit\Framework\MockObject\MockObject */
        $collection = $this->getMockBuilder(Collection::class)
            ->getMock();

        $collection->expects($this->never())
            ->method('send');

        /** @var System&\PHPUnit\Framework\MockObject\MockObject */
        $system = $this->getMockBuilder(System::class)
            ->getMock();

        $system->expects($this->never())
            ->method('send');

        $container = new Container($resolver, $collection, $system);

        // Act
        $container->specify(
            [Fixture\SimpleService::class, Fixture\SimpleClass::class],
            Fixture\SimpleDependency::class,
            Fixture\SimpleDependency::class
        );
    }

    public function testContainerDefaultConstructor(): void
    {
        // Arrange & Act
        $container = new Container();

        // Assert — container is functional with default resolver, middleware, and decorators
        $this->assertFalse($container->has(Fixture\SimpleService::class));
        $result = $container->get(Fixture\SimpleDependency::class);
        $this->assertInstanceOf(Fixture\SimpleDependency::class, $result);
    }

    public function testCircularDependencyThrowsWithChain(): void
    {
        // Arrange
        $container = new Container();

        // Act & Assert
        // Codex detects the cycle during reflection-based resolution before
        // Cabinet's own tracking triggers. Both throw RuntimeException with the
        // same message shape; Cabinet's CircularDependency will be caught when
        // the cycle occurs at the provider/get() level instead.
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circular dependency detected:');

        $container->get(Fixture\CircularA::class);
    }

    public function testSelfRegistersAsApplication(): void
    {
        $container = new Container();

        $this->assertSame($container, $container->get(\Arcanum\Cabinet\Application::class));
    }

    public function testSelfRegistersAsContainerInterface(): void
    {
        $container = new Container();

        $this->assertSame($container, $container->get(\Psr\Container\ContainerInterface::class));
    }
}
