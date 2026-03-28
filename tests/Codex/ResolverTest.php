<?php

declare(strict_types=1);

namespace Arcanum\Test\Codex;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Arcanum\Test\Fixture;
use Arcanum\Codex\Error;
use Arcanum\Codex\Resolver;
use Arcanum\Codex\EventDispatcher;
use Arcanum\Codex\ClassNameResolver;
use Arcanum\Codex\PrimitiveResolver;
use Arcanum\Codex\Event\CodexEvent;
use Arcanum\Codex\Event\ClassResolved;
use Arcanum\Codex\Event\ClassRequested;
use Psr\Container\ContainerInterface;

#[CoversClass(Resolver::class)]
#[CoversClass(ClassNameResolver::class)]
#[CoversClass(PrimitiveResolver::class)]
#[UsesClass(Error\Unresolvable::class)]
#[UsesClass(Error\UnresolvableClass::class)]
#[UsesClass(Error\UnresolvablePrimitive::class)]
#[UsesClass(Error\UnresolvableUnionType::class)]
#[UsesClass(ClassResolved::class)]
#[UsesClass(ClassRequested::class)]
#[UsesClass(Fixture\ServiceWithNullableDependency::class)]
final class ResolverTest extends TestCase
{
    public function testClosure(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->never())
            ->method('has');

        $resolver = Resolver::forContainer($container);

        // Act
        $resolved = $resolver->resolve(fn (): object => new \stdClass());

        // Assert
        $this->assertInstanceOf(\stdClass::class, $resolved);
    }

    public function testClassWithNoConstructor(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->once())
            ->method('has')
            ->with(Fixture\SimpleDependency::class)
            ->willReturn(false);

        $resolver = Resolver::forContainer($container);

        // Act
        $resolved = $resolver->resolve(Fixture\SimpleClass::class);

        // Assert
        $this->assertInstanceOf(Fixture\SimpleClass::class, $resolved);
    }

    public function testClassWithConstructorThatHasNoParameters(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->never())
            ->method('has');

        $resolver = Resolver::forContainer($container);

        // Act
        $resolved = $resolver->resolve(Fixture\ConcreteService::class);

        // Assert
        $this->assertInstanceOf(Fixture\ConcreteService::class, $resolved);
    }

    public function testClassWithDefaultPrimitives(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->never())
            ->method('has');

        $resolver = Resolver::forContainer($container);

        // Act
        $resolved = $resolver->resolve(Fixture\PrimitiveService::class);

        // Assert
        $this->assertInstanceOf(Fixture\PrimitiveService::class, $resolved);
        $this->assertSame("", $resolved->getString());
        $this->assertSame(0, $resolved->getInt());
        $this->assertSame(0.0, $resolved->getFloat());
        $this->assertSame(false, $resolved->getBool());
        $this->assertSame([], $resolved->getArray());
        $this->assertInstanceOf(\stdClass::class, $resolved->getObject());
        $this->assertInstanceOf(Fixture\SimpleDependency::class, $resolved->getMixed());
        $this->assertSame(null, $resolved->getNull());
    }

    public function testVariadicPrimitives(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->never())
            ->method('has');

        $resolver = Resolver::forContainer($container);

        // Act
        $resolved = $resolver->resolve(Fixture\VariadicPrimitiveService::class);

        // Assert
        $this->assertInstanceOf(Fixture\VariadicPrimitiveService::class, $resolved);
        $this->assertSame([], $resolved->strings);
    }

    public function testDefaultValuesWithNoTypeHint(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->never())
            ->method('has');

        $resolver = Resolver::forContainer($container);

        // Act
        $resolved = $resolver->resolve(Fixture\DefaultPrimitiveServiceWithNoType::class);

        // Assert
        $this->assertInstanceOf(Fixture\DefaultPrimitiveServiceWithNoType::class, $resolved);
        $this->assertSame(0, $resolved->test);
    }

    public function testParentKeywordInDependencies(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->once())
            ->method('has')
            ->with(Fixture\SimpleDependency::class)
            ->willReturn(false);

        $resolver = Resolver::forContainer($container);

        // Act
        $resolved = $resolver->resolve(Fixture\ParentClass::class);

        // Assert
        $this->assertInstanceOf(Fixture\ParentClass::class, $resolved);
        $this->assertInstanceOf(Fixture\SimpleDependency::class, $resolved->dependency);
    }

    public function testAnUninstantiableClass(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->never())
            ->method('has');

        $resolver = Resolver::forContainer($container);

        // Assert
        $this->expectException(Error\UnresolvableClass::class);

        // Act
        $resolver->resolve(Fixture\AbstractService::class);
    }

    public function testVariadicClassServiceWithNoSpecification(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->never())
            ->method('has');

        $resolver = Resolver::forContainer($container);

        // Act
        $service = $resolver->resolve(Fixture\VariadicClassService::class);

        // Assert
        $this->assertInstanceOf(Fixture\VariadicClassService::class, $service);
        $this->assertSame([], $service->dependencies);
    }

    public function testClassWithPrimitivesThatHaveNoDefaults(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->once())
            ->method('has')
            ->with(Fixture\DependencyWithNoDefaultPrimitive::class)
            ->willReturn(false);

        $resolver = Resolver::forContainer($container);

        // Assert
        $this->expectException(Error\UnresolvablePrimitive::class);

        // Act
        $resolver->resolve(Fixture\ServiceWithNoDefaultPrimitive::class);
    }

    public function testClassThatHasUnresolvablePrimitivesButWithADefault(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->once())
            ->method('has')
            ->with(Fixture\DependencyWithNoDefaultPrimitive::class)
            ->willReturn(false);

        $resolver = Resolver::forContainer($container);

        // Act
        $resolved = $resolver->resolve(Fixture\ParentPrimitiveService::class);

        // Assert
        $this->assertInstanceOf(Fixture\ParentPrimitiveService::class, $resolved);
    }

    public function testCallableFunction(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->never())
            ->method('has');

        $resolver = Resolver::forContainer($container);

        // Assert
        $this->expectException(Error\UnresolvablePrimitive::class);

        // Act
        $resolver->resolve(Fixture\FunctionService::class);
    }

    public function testUnionType(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->never())
            ->method('has');

        $resolver = Resolver::forContainer($container);

        // Assert
        $this->expectException(Error\UnresolvableUnionType::class);

        // Act
        $resolver->resolve(Fixture\ServiceWithUnionType::class);
    }

    public function testPreferContainerInstancesOverNewInstances(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['has', 'get'])
            ->getMock();

        $dependency = new Fixture\SimpleDependency();

        $container->expects($this->once())
            ->method('has')
            ->with(Fixture\SimpleDependency::class)
            ->willReturn(true);

        $container->expects($this->once())
            ->method('get')
            ->with(Fixture\SimpleDependency::class)
            ->willReturn($dependency);

        $resolver = Resolver::forContainer($container);

        // Act
        $resolved = $resolver->resolve(Fixture\SimpleClass::class);

        // Assert
        $this->assertSame($dependency, $resolved->dependency);
    }

    public function testCodexEventDispatcher(): void
    {
        // Arrange
        $dispatcher = $this->getMockBuilder(EventDispatcher::class)
            ->onlyMethods(['dispatch'])
            ->getMock();

        $matcher = $this->exactly(5);
        $dispatcher->expects($matcher)
            ->method('dispatch')
            ->willReturnCallback(fn(CodexEvent $event) =>
                match ($matcher->numberOfInvocations()) {
                    0 => $this->fail('ClassResolved event should have been dispatched.'),
                    1 => $this->assertTrue(
                        $event instanceof ClassResolved &&
                        $event->class() === $dispatcher,
                        "First event was not ClassResolved, or the service was not the dispatcher."
                    ),
                    2 => $this->assertTrue(
                        $event instanceof ClassRequested &&
                        $event->className() === Fixture\SimpleClass::class,
                        "Second event was not ClassRequested, or the service name was not SimpleClass."
                    ),
                    3 => $this->assertTrue(
                        $event instanceof ClassRequested &&
                        $event->className() === Fixture\SimpleDependency::class,
                        "Third event was not ClassRequested, or the service name was not SimpleDependency."
                    ),
                    4 => $this->assertTrue(
                        $event instanceof ClassResolved &&
                        $event->class() instanceof Fixture\SimpleDependency,
                        "Fourth event was not ClassResolved, or the service was not an instance of SimpleDependency."
                    ),
                    5 => $this->assertTrue(
                        $event instanceof ClassResolved &&
                        $event->class() instanceof Fixture\SimpleClass,
                        "Fifth event was not ClassResolved, or the service was not an instance of SimpleClass."
                    ),
                    default => $this->fail('Too many events dispatched.'),
                });

        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['has', 'get'])
            ->getMock();

        $container->expects($this->once())
            ->method('has')
            ->with(Fixture\SimpleDependency::class)
            ->willReturn(false);

        $container->expects($this->never())
            ->method('get');

        $resolver = Resolver::forContainer($container);

        // Act
        $resolver->resolve(fn() => $dispatcher);
        $resolver->resolve(Fixture\SimpleClass::class);
    }

    public function testResolveWith(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['has', 'get'])
            ->getMock();

        $dependency = new Fixture\SimpleDependency();

        $container->expects($this->once())
            ->method('has')
            ->with(Fixture\ConcreteService::class)
            ->willReturn(false);

        $container->expects($this->never())
            ->method('get');

        $resolver = Resolver::forContainer($container);

        // Act
        $resolved = $resolver->resolveWith(Fixture\ServiceWithInterface::class, [
            Fixture\ConcreteService::class
        ]);

        // Assert
        $this->assertInstanceOf(Fixture\ConcreteService::class, $resolved->dependency);
    }

    public function testResolveWithFailsIfClassNameIsNotInstantiable(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['has', 'get'])
            ->getMock();

        $container->expects($this->never())
            ->method('has');

        $container->expects($this->never())
            ->method('get');

        $resolver = Resolver::forContainer($container);

        // Assert
        $this->expectException(Error\UnresolvableClass::class);

        // Act
        $resolver->resolveWith(Fixture\AbstractService::class, []);
    }

    public function testResolveWithResolvesWhenClassHasNoConstructor(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['has', 'get'])
            ->getMock();

        $container->expects($this->never())
            ->method('has');

        $container->expects($this->never())
            ->method('get');

        $resolver = Resolver::forContainer($container);

        // Act
        $resolved = $resolver->resolveWith(Fixture\SimpleDependency::class, []);

        // Assert
        $this->assertInstanceOf(Fixture\SimpleDependency::class, $resolved);
    }

    public function testResolveWithResolvesWhenConstructorHasNoParameters(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['has', 'get'])
            ->getMock();

        $container->expects($this->never())
            ->method('has');

        $container->expects($this->never())
            ->method('get');

        $resolver = Resolver::forContainer($container);

        // Act
        $resolved = $resolver->resolveWith(Fixture\ConcreteService::class, []);

        // Assert
        $this->assertInstanceOf(Fixture\ConcreteService::class, $resolved);
    }

    public function testResolveWithIsUnresolvableIfArgumentCountIsLessThanParameterCount(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['has', 'get'])
            ->getMock();

        $container->expects($this->never())
            ->method('has');

        $container->expects($this->never())
            ->method('get');

        $resolver = Resolver::forContainer($container);

        // Assert
        $this->expectException(Error\UnresolvableClass::class);

        // Act
        $resolver->resolveWith(Fixture\ServiceWithInterface::class, []);
    }

    public function testResolveWithIsResolvableIfArgumentCountIsLessThanParameterCountWithDefaults(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['has', 'get'])
            ->getMock();

        $container->expects($this->never())
            ->method('has');

        $container->expects($this->never())
            ->method('get');

        $resolver = Resolver::forContainer($container);

        // Act
        $resolved = $resolver->resolveWith(Fixture\ParentPrimitiveService::class, []);

        // Assert
        $this->assertInstanceOf(Fixture\ParentPrimitiveService::class, $resolved);
        $this->assertInstanceOf(Fixture\DependencyWithNoDefaultPrimitive::class, $resolved->dependency);
    }

    public function testSpecifyVariableParameter(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->never())
            ->method('has');

        $resolver = Resolver::forContainer($container);

        // Act
        $resolver->specify(Fixture\DependencyWithNoDefaultPrimitive::class, '$string', 'foo');
        $resolver->specify(Fixture\DependencyWithNoDefaultPrimitive::class, '$int', 42);
        $resolver->specify(Fixture\DependencyWithNoDefaultPrimitive::class, '$float', 42.42);
        $resolver->specify(Fixture\DependencyWithNoDefaultPrimitive::class, '$bool', true);
        $resolver->specify(Fixture\DependencyWithNoDefaultPrimitive::class, '$array', ['a', 'b', 'c']);
        $resolver->specify(Fixture\DependencyWithNoDefaultPrimitive::class, '$object', new \stdClass());
        $resolver->specify(Fixture\DependencyWithNoDefaultPrimitive::class, '$mixed', 'foo two');
        $resolver->specify(Fixture\DependencyWithNoDefaultPrimitive::class, '$null', null);

        $resolved = $resolver->resolve(Fixture\DependencyWithNoDefaultPrimitive::class);

        // Assert
        $this->assertSame('foo', $resolved->getString());
        $this->assertSame(42, $resolved->getInt());
        $this->assertSame(42.42, $resolved->getFloat());
        $this->assertTrue($resolved->getBool());
        $this->assertSame(['a', 'b', 'c'], $resolved->getArray());
        $this->assertInstanceOf(\stdClass::class, $resolved->getObject());
        $this->assertSame('foo two', $resolved->getMixed());
        $this->assertSame(null, $resolved->getNull());
    }

    public function testSpecifyInterfaceParameter(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->once())
            ->method('has')
            ->with(Fixture\ServiceImplementsInterface::class)
            ->willReturn(false);

        $resolver = Resolver::forContainer($container);

        // Act
        $resolver->specify(
            when: Fixture\ServiceWithInterface::class,
            needs: Fixture\ServiceInterface::class,
            give: Fixture\ServiceImplementsInterface::class
        );

        $resolved = $resolver->resolve(Fixture\ServiceWithInterface::class);

        // Assert
        $this->assertInstanceOf(Fixture\ServiceImplementsInterface::class, $resolved->dependency);
    }

    public function testSpecifyArrayOfClasses(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->exactly(2))
            ->method('has')
            ->with(Fixture\ServiceImplementsInterface::class)
            ->willReturn(false);

        $resolver = Resolver::forContainer($container);

        // Act
        $resolver->specify(
            when: [
                Fixture\ServiceWithInterface::class,
                Fixture\AnotherServiceWithInterface::class
            ],
            needs: Fixture\ServiceInterface::class,
            give: Fixture\ServiceImplementsInterface::class
        );

        $serviceA = $resolver->resolve(Fixture\ServiceWithInterface::class);
        $serviceB = $resolver->resolve(Fixture\AnotherServiceWithInterface::class);

        // Assert
        $this->assertInstanceOf(Fixture\ServiceImplementsInterface::class, $serviceA->dependency);
        $this->assertInstanceOf(Fixture\ServiceImplementsInterface::class, $serviceB->dependency);
    }

    public function testSpecifyVariadicClassService(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->exactly(3))
            ->method('get')
            ->with(Fixture\SimpleDependency::class)
            ->willReturn(new Fixture\SimpleDependency());

        $container->expects($this->exactly(3))
            ->method('has')
            ->with(Fixture\SimpleDependency::class)
            ->willReturn(true);

        $resolver = Resolver::forContainer($container);

        // Act
        $resolver->specify(
            when: Fixture\VariadicClassService::class,
            needs: Fixture\SimpleDependency::class,
            give: [
                Fixture\SimpleDependency::class,
                Fixture\SimpleDependency::class,
                Fixture\SimpleDependency::class,
            ]
        );

        $service = $resolver->resolve(Fixture\VariadicClassService::class);

        // Assert
        $this->assertCount(3, $service->dependencies);
    }

    public function testSpecifyWithClosure(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->never())
            ->method('has');

        $resolver = Resolver::forContainer($container);

        // Act
        $resolver->specify(
            when: Fixture\ServiceWithInterface::class,
            needs: Fixture\ServiceInterface::class,
            give: fn () => new Fixture\ServiceImplementsInterface()
        );

        $resolved = $resolver->resolve(Fixture\ServiceWithInterface::class);

        // Assert
        $this->assertInstanceOf(Fixture\ServiceImplementsInterface::class, $resolved->dependency);
    }

    public function testSpecifyWithInstance(): void
    {
        // Arrange
        /** @var ContainerInterface&\PHPUnit\Framework\MockObject\MockObject */
        $container = $this->getMockBuilder(ContainerInterface::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'has'])
            ->getMock();

        $container->expects($this->never())
            ->method('get');

        $container->expects($this->never())
            ->method('has');

        $resolver = Resolver::forContainer($container);

        // Act
        $resolver->specify(
            when: Fixture\ServiceWithInterface::class,
            needs: Fixture\ServiceInterface::class,
            give: new Fixture\ServiceImplementsInterface()
        );

        $resolved = $resolver->resolve(Fixture\ServiceWithInterface::class);

        // Assert
        $this->assertInstanceOf(Fixture\ServiceImplementsInterface::class, $resolved->dependency);
    }

    public function testNullableParameterFallsBackToNullWhenUnresolvable(): void
    {
        // Arrange
        $container = $this->createStub(ContainerInterface::class);

        $container->method('has')
            ->willReturn(false);

        $resolver = Resolver::forContainer($container);
        $resolver->specify(Fixture\ServiceWithNullableDependency::class, '$name', 'test');

        // Act
        $resolved = $resolver->resolve(Fixture\ServiceWithNullableDependency::class);

        // Assert
        $this->assertInstanceOf(Fixture\ServiceWithNullableDependency::class, $resolved);
        $this->assertSame('test', $resolved->name);
        $this->assertNull($resolved->logger);
    }
}
