<?php

declare(strict_types=1);

namespace Arcanum\Test\Codex;

use Arcanum\Codex\Hydrator;
use Arcanum\Codex\Error;
use Arcanum\Test\Fixture;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Hydrator::class)]
#[UsesClass(Error\UnresolvableClass::class)]
#[UsesClass(\Arcanum\Flow\Conveyor\EmptyDTO::class)]
final class HydratorTest extends TestCase
{
    // ---------------------------------------------------------------
    // Basic hydration
    // ---------------------------------------------------------------

    public function testHydrateWithAllParameters(): void
    {
        // Arrange
        $hydrator = new Hydrator();

        // Act
        $dto = $hydrator->hydrate(Fixture\ContactQuery::class, [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'message' => 'Hello!',
        ]);

        // Assert
        $this->assertInstanceOf(Fixture\ContactQuery::class, $dto);
        $this->assertSame('Alice', $dto->name);
        $this->assertSame('alice@example.com', $dto->email);
        $this->assertSame('Hello!', $dto->message);
    }

    public function testHydrateFallsBackToDefaultValues(): void
    {
        // Arrange
        $hydrator = new Hydrator();

        // Act
        $dto = $hydrator->hydrate(Fixture\ContactQuery::class, [
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);

        // Assert
        $this->assertSame('Bob', $dto->name);
        $this->assertSame('bob@example.com', $dto->email);
        $this->assertSame('', $dto->message);
    }

    public function testHydrateWithNoConstructorParams(): void
    {
        // Arrange
        $hydrator = new Hydrator();

        // Act
        $dto = $hydrator->hydrate(\Arcanum\Flow\Conveyor\EmptyDTO::class, []);

        // Assert
        $this->assertInstanceOf(\Arcanum\Flow\Conveyor\EmptyDTO::class, $dto);
    }

    public function testHydrateWithAllDefaults(): void
    {
        // Arrange
        $hydrator = new Hydrator();

        // Act — no data provided, all params have defaults
        $dto = $hydrator->hydrate(Fixture\PaginatedQuery::class, []);

        // Assert
        $this->assertSame(1, $dto->page);
        $this->assertSame(25, $dto->perPage);
        $this->assertFalse($dto->includeArchived);
    }

    // ---------------------------------------------------------------
    // Type coercion
    // ---------------------------------------------------------------

    public function testCoercesStringToInt(): void
    {
        // Arrange
        $hydrator = new Hydrator();

        // Act — query params are always strings
        $dto = $hydrator->hydrate(Fixture\PaginatedQuery::class, [
            'page' => '3',
            'perPage' => '50',
        ]);

        // Assert
        $this->assertSame(3, $dto->page);
        $this->assertSame(50, $dto->perPage);
    }

    public function testCoercesStringToBoolTrue(): void
    {
        // Arrange
        $hydrator = new Hydrator();

        // Act
        $dto = $hydrator->hydrate(Fixture\PaginatedQuery::class, [
            'includeArchived' => 'true',
        ]);

        // Assert
        $this->assertTrue($dto->includeArchived);
    }

    public function testCoercesStringToBoolFalse(): void
    {
        // Arrange
        $hydrator = new Hydrator();

        // Act
        $dto = $hydrator->hydrate(Fixture\PaginatedQuery::class, [
            'includeArchived' => 'false',
        ]);

        // Assert
        $this->assertFalse($dto->includeArchived);
    }

    public function testCoercesBoolOneAndZero(): void
    {
        // Arrange
        $hydrator = new Hydrator();

        // Act
        $trueDto = $hydrator->hydrate(Fixture\PaginatedQuery::class, [
            'includeArchived' => '1',
        ]);
        $falseDto = $hydrator->hydrate(Fixture\PaginatedQuery::class, [
            'includeArchived' => '0',
        ]);

        // Assert
        $this->assertTrue($trueDto->includeArchived);
        $this->assertFalse($falseDto->includeArchived);
    }

    public function testCoercesStringToFloat(): void
    {
        // Arrange
        $hydrator = new Hydrator();

        // Act
        $dto = $hydrator->hydrate(Fixture\PrimitiveService::class, [
            'float' => '3.14',
        ]);

        // Assert
        $this->assertSame(3.14, $dto->getFloat());
    }

    public function testStringParamsPassedThrough(): void
    {
        // Arrange
        $hydrator = new Hydrator();

        // Act
        $dto = $hydrator->hydrate(Fixture\ContactQuery::class, [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        // Assert — strings pass through unchanged
        $this->assertSame('Alice', $dto->name);
        $this->assertSame('alice@example.com', $dto->email);
    }

    // ---------------------------------------------------------------
    // Missing required parameter
    // ---------------------------------------------------------------

    public function testThrowsWhenRequiredParameterMissing(): void
    {
        // Arrange
        $hydrator = new Hydrator();

        // Act & Assert
        $this->expectException(Error\UnresolvableClass::class);
        $this->expectExceptionMessage('$name');
        $hydrator->hydrate(Fixture\ContactQuery::class, [
            'email' => 'alice@example.com',
        ]);
    }

    // ---------------------------------------------------------------
    // Extra data is ignored
    // ---------------------------------------------------------------

    public function testExtraDataIsIgnored(): void
    {
        // Arrange
        $hydrator = new Hydrator();

        // Act
        $dto = $hydrator->hydrate(Fixture\ContactQuery::class, [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'extra' => 'ignored',
            'another' => 'also ignored',
        ]);

        // Assert
        $this->assertSame('Alice', $dto->name);
        $this->assertSame('alice@example.com', $dto->email);
    }

    // ---------------------------------------------------------------
    // Non-instantiable class
    // ---------------------------------------------------------------

    public function testThrowsForNonInstantiableClass(): void
    {
        // Arrange
        $hydrator = new Hydrator();

        // Act & Assert
        $this->expectException(Error\UnresolvableClass::class);
        $hydrator->hydrate(Fixture\ServiceInterface::class, []);
    }
}
