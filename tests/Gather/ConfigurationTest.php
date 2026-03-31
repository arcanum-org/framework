<?php

declare(strict_types=1);

namespace Arcanum\Test\Gather;

use Arcanum\Gather\Configuration;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(Configuration::class)]
#[UsesClass(\Arcanum\Gather\Registry::class)]
final class ConfigurationTest extends TestCase
{
    public function testOffsetGetReturnsValue(): void
    {
        // Arrange
        $configuration = new Configuration([
            'parent-key' => [
                'child-key-1' => [
                    'child-key-2' => 'value'
                ]
            ]
        ]);

        // Act
        $value = $configuration['parent-key.child-key-1.child-key-2'];

        // Assert
        $this->assertSame('value', $value);
    }

    public function testOffsetGetReturnsNullIfChildKeyIsInvalid(): void
    {
        // Arrange
        $configuration = new Configuration([
            'parent-key' => [
                'child-key-1' => [
                    'child-key-2' => 'value'
                ]
            ]
        ]);

        // Act
        $value = $configuration['parent-key.child-key-C.child-key-2'];

        // Assertl
        $this->assertNull($value);
    }

    public function testOffsetGetReturnsSubset(): void
    {
        // Arrange
        $configuration = new Configuration([
            'parent-key' => [
                'child-key-1' => [
                    'child-key-2' => 'value'
                ]
            ]
        ]);

        // Act
        $value = $configuration['parent-key.child-key-1'];
        $parent = $configuration['parent-key'];

        // Assert
        $this->assertSame(['child-key-2' => 'value'], $value);
        $this->assertSame(['child-key-1' => ['child-key-2' => 'value']], $parent);
    }

    public function testOffsetSetHandlesSubArrays(): void
    {
        // Arrange
        $configuration = new Configuration();

        // Act
        $configuration['parent-key.child-key-1.child-key-2'] = [ 'value' ];

        // Assert
        $this->assertSame('value', $configuration['parent-key.child-key-1.child-key-2.0']);
        $this->assertSame([ 'value' ], $configuration['parent-key.child-key-1.child-key-2']);
        $this->assertSame(['child-key-2' => [ 'value' ]], $configuration['parent-key.child-key-1']);
        $this->assertSame(['child-key-1' => ['child-key-2' => [ 'value']]], $configuration['parent-key']);
    }

    public function testOffsetSetHandlesSubArraysWithExistingData(): void
    {
        // Arrange
        $configuration = new Configuration([
            'parent-key' => [
                'child-key-1' => [
                    'child-key-2' => 'value'
                ]
            ]
        ]);

        // Act
        $configuration['parent-key.child-key-1.child-key-2'] = [ 'value' ];

        // Assert
        $this->assertSame('value', $configuration['parent-key.child-key-1.child-key-2.0']);
        $this->assertSame([ 'value' ], $configuration['parent-key.child-key-1.child-key-2']);
        $this->assertSame(['child-key-2' => [ 'value' ]], $configuration['parent-key.child-key-1']);
        $this->assertSame(['child-key-1' => ['child-key-2' => [ 'value']]], $configuration['parent-key']);
    }

    public function testOffsetSetHandlesSubArraysWithExistingDataDifferentKey(): void
    {
        // Arrange
        $configuration = new Configuration([
            'parent-key' => [
                'child-key-1' => [
                    'child-key-2' => 'value'
                ],
                'side-key' => 'side-value'
            ]
        ]);

        // Act
        $configuration['parent-key.side-key.child-key-3'] = 'value';

        // Assert
        $this->assertSame('value', $configuration['parent-key.side-key.child-key-3']);
        $this->assertSame(['child-key-2' => 'value'], $configuration['parent-key.child-key-1']);
        $this->assertSame(['child-key-3' => 'value' ], $configuration['parent-key.side-key']);
        $this->assertSame([
            'child-key-1' => [
                'child-key-2' => 'value'
            ],
            'side-key' => [
                'child-key-3' => 'value'
            ]
        ], $configuration['parent-key']);
    }

    public function testOffsetExistsHandlesSubArrays(): void
    {
        // Arrange
        $configuration = new Configuration([
            'parent-key' => [
                'child-key-1' => [
                    'child-key-2' => 'value'
                ]
            ]
        ]);

        // Act
        $parent = isset($configuration['parent-key']);
        $child = isset($configuration['parent-key.child-key-1']);
        $grandchild = isset($configuration['parent-key.child-key-1.child-key-2']);
        $invalid = isset($configuration['parent-key.child-key-1.child-key-3']);

        // Assert
        $this->assertTrue($parent);
        $this->assertTrue($child);
        $this->assertTrue($grandchild);
        $this->assertFalse($invalid);
    }

    public function testOffsetUnsetHandlesSubArrays(): void
    {
        // Arrange
        $configuration = new Configuration([
            'parent-key' => [
                'child-key-1' => [
                    'child-key-2' => 'value'
                ]
            ]
        ]);

        // Act
        unset($configuration['parent-key.child-key-1.child-key-2']);

        // Assert
        $this->assertNull($configuration['parent-key.child-key-1.child-key-2']);

        // Act
        unset($configuration['parent-key.child-key-1']);

        // Assert
        $this->assertNull($configuration['parent-key.child-key-1']);

        // Act
        unset($configuration['parent-key']);

        // Assert
        $this->assertNull($configuration['parent-key']);
    }

    public function testOffsetUnsetInvalidChildKeyDoesNothing(): void
    {
        // Arrange
        $configuration = new Configuration([
            'parent-key' => [
                'child-key-1' => [
                    'child-key-2' => 'value'
                ]
            ]
        ]);

        // Act
        unset($configuration['parent-key.child-key-B.child-key-2']);

        // Assert
        $this->assertSame(['child-key-2' => 'value'], $configuration['parent-key.child-key-1']);
    }

    public function testAsTypeFunctionsHonorSubArraySyntax(): void
    {
        // Arrange
        $configuration = new Configuration([
            'parent-key' => [
                'child-key-1' => [
                    'child-key-2' => [
                        'child-key-3' => '1'
                    ]
                ]
            ]
        ]);

        // Act
        $string = $configuration->asString('parent-key.child-key-1.child-key-2.child-key-3');
        $int = $configuration->asInt('parent-key.child-key-1.child-key-2.child-key-3');
        $float = $configuration->asFloat('parent-key.child-key-1.child-key-2.child-key-3');
        $bool = $configuration->asBool('parent-key.child-key-1.child-key-2.child-key-3');

        // Assert
        $this->assertSame('1', $string);
        $this->assertSame(1, $int);
        $this->assertSame(1.0, $float);
        $this->assertTrue($bool);
    }

    public function testAsAlphaWithDotNotationKey(): void
    {
        // Arrange
        $configuration = new Configuration([
            'app' => [
                'name' => '1b2a3r4'
            ]
        ]);

        // Act
        $alpha = $configuration->asAlpha('app.name');

        // Assert
        $this->assertSame('bar', $alpha);
    }

    public function testAsAlnumWithDotNotationKey(): void
    {
        // Arrange
        $configuration = new Configuration([
            'app' => [
                'version' => '1b2a@3r4'
            ]
        ]);

        // Act
        $alnum = $configuration->asAlnum('app.version');

        // Assert
        $this->assertSame('1b2a3r4', $alnum);
    }

    public function testAsDigitsWithDotNotationKey(): void
    {
        // Arrange
        $configuration = new Configuration([
            'app' => [
                'port' => '80ab80'
            ]
        ]);

        // Act
        $digits = $configuration->asDigits('app.port');

        // Assert
        $this->assertSame('8080', $digits);
    }

    public function testSetOverwritesScalarIntermediatePathValue(): void
    {
        // Arrange
        $configuration = new Configuration([
            'database' => [
                'host' => 'localhost'
            ]
        ]);

        // Act — 'host' is a scalar, but we set a child key under it
        $configuration->set('database.host.primary', '10.0.0.1');

        // Assert — scalar was replaced with an array
        $this->assertSame('10.0.0.1', $configuration->get('database.host.primary'));
        $this->assertSame(['primary' => '10.0.0.1'], $configuration->get('database.host'));
    }
}
