<?php

declare(strict_types=1);

namespace Arcanum\Test\Hyper\Files;

use Arcanum\Hyper\Files\UploadedFile;
use Arcanum\Hyper\Files\UploadedFiles;
use Arcanum\Hyper\Files\Error;
use Arcanum\Hyper\Files\Normalizer;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

#[CoversClass(UploadedFiles::class)]
#[CoversClass(UploadedFile::class)]
#[CoversClass(Normalizer::class)]
#[UsesClass(Error::class)]
final class UploadedFilesTest extends TestCase
{
    public function testFromSimpleSpec(): void
    {
        // Arrange
        // $_FILES fixture from https://www.php-fig.org/psr/psr-7/ spec
        $files = [
            'avatar' => [
                'tmp_name' => 'phpUxcOty',
                'name' => 'my-avatar.png',
                'size' => 90996,
                'type' => 'image/png',
                'error' => 0,
            ]
        ];

        // Act
        $result = UploadedFiles::fromArray($files['avatar']);

        // Assert
        $this->assertInstanceOf(UploadedFiles::class, $result);
    }

    public function testFromArraySpec(): void
    {
        // Arrange
        // $_FILES fixture from https://www.php-fig.org/psr/psr-7/ spec
        $files = [
            'my-form' => [
                'name' => [
                    'details' => [
                        'avatar' => 'my-avatar.png',
                    ],
                ],
                'type' => [
                    'details' => [
                        'avatar' => 'image/png',
                    ],
                ],
                'tmp_name' => [
                    'details' => [
                        'avatar' => 'phpmFLrzD',
                    ],
                ],
                'error' => [
                    'details' => [
                        'avatar' => 0,
                    ],
                ],
                'size' => [
                    'details' => [
                        'avatar' => 90996,
                    ],
                ],
            ],
        ];

        // Act
        $result = UploadedFiles::fromArray($files)->toArray();

        // Assert
        $this->assertArrayHasKey('my-form', $result);
        $this->assertIsArray($result['my-form']);
        $this->assertArrayHasKey('details', $result['my-form']);
        $this->assertIsArray($result['my-form']['details']);
        $this->assertArrayHasKey('avatar', $result['my-form']['details']);
        $this->assertInstanceOf(UploadedFile::class, $result['my-form']['details']['avatar']);
    }

    public function testFromArrayMultiFile(): void
    {
        // Arrange
        // $_FILES fixture from https://www.php-fig.org/psr/psr-7/ spec
        $files = [
            'my-form' => [
                'name' => [
                    'details' => [
                        'avatars' => [
                            0 => 'my-avatar.png',
                            1 => 'my-avatar2.png',
                            2 => 'my-avatar3.png',
                        ],
                    ],
                ],
                'type' => [
                    'details' => [
                        'avatars' => [
                            0 => 'image/png',
                            1 => 'image/png',
                            2 => 'image/png',
                        ],
                    ],
                ],
                'tmp_name' => [
                    'details' => [
                        'avatars' => [
                            0 => 'phpmFLrzD',
                            1 => 'phpV2pBil',
                            2 => 'php8RUG8v',
                        ],
                    ],
                ],
                'error' => [
                    'details' => [
                        'avatars' => [
                            0 => 0,
                            1 => 0,
                            2 => 0,
                        ],
                    ],
                ],
                'size' => [
                    'details' => [
                        'avatars' => [
                            0 => 90996,
                            1 => 90996,
                            3 => 90996,
                        ],
                    ],
                ],
            ],
        ];

        // Act
        $result = UploadedFiles::fromArray($files)->toArray();

        // Assert
        $this->assertArrayHasKey('my-form', $result);
        $this->assertIsArray($result['my-form']);
        $this->assertArrayHasKey('details', $result['my-form']);
        $this->assertIsArray($result['my-form']['details']);
        $this->assertArrayHasKey('avatars', $result['my-form']['details']);
        $this->assertIsArray($result['my-form']['details']['avatars']);
        $this->assertCount(3, $result['my-form']['details']['avatars']);
        $this->assertInstanceOf(UploadedFile::class, $result['my-form']['details']['avatars'][0]);
        $this->assertInstanceOf(UploadedFile::class, $result['my-form']['details']['avatars'][1]);
        $this->assertInstanceOf(UploadedFile::class, $result['my-form']['details']['avatars'][2]);
    }

    public function testNormalizePassesThroughUploadedFileInterface(): void
    {
        // Arrange
        $uploadedFile = new UploadedFile(
            file: null,
            mode: 'r',
            error: Error::UPLOAD_ERR_NO_FILE,
        );

        // Act
        $result = UploadedFiles::fromArray(['avatar' => $uploadedFile])->toArray();

        // Assert
        $this->assertSame($uploadedFile, $result['avatar']);
    }

    public function testNormalizeThrowsForInvalidTmpName(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        UploadedFiles::fromArray([
            'avatar' => [
                'tmp_name' => 12345,
                'error' => 0,
            ],
        ])->toArray();
    }

    public function testNormalizeRecursesNestedArraysWithoutTmpName(): void
    {
        // Arrange
        $uploadedFile = new UploadedFile(
            file: null,
            mode: 'r',
            error: Error::UPLOAD_ERR_NO_FILE,
        );

        // Act
        $result = UploadedFiles::fromArray([
            'nested' => [
                'inner' => $uploadedFile,
            ],
        ])->toArray();

        // Assert
        $this->assertIsArray($result['nested']);
        $this->assertSame($uploadedFile, $result['nested']['inner']);
    }

    public function testNormalizeThrowsForInvalidValue(): void
    {
        // Assert
        $this->expectException(\InvalidArgumentException::class);

        // Act
        UploadedFiles::fromArray([
            'bad' => 'not-valid',
        ])->toArray();
    }

    public function testNormalizeSpecReturnsEmptyWhenTmpNameIsStringButOtherParamIsArray(): void
    {
        // Arrange — tmp_name is a string, but size is an array, so containsArray
        // returns true and normalizeSpec is called. Since tmp_name is not an array,
        // normalizeSpec returns early with an empty array.
        $result = Normalizer::fromSpec(
            tmpName: 'phpUxcOty',
            size: [90996],
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testNormalizeSpecSkipsNonStringNonArrayTmpNameValues(): void
    {
        // Arrange — tmp_name array contains an integer value, which should be skipped
        $result = Normalizer::fromSpec(
            tmpName: ['valid' => 'phpUxcOty', 'invalid' => 12345],
            error: ['valid' => 0, 'invalid' => 0],
        );

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('valid', $result);
        $this->assertInstanceOf(UploadedFile::class, $result['valid']);
        $this->assertArrayNotHasKey('invalid', $result);
    }

    public function testFromSuperGlobal(): void
    {
        // Arrange
        $_FILES['avatar'] = [
            'tmp_name' => 'phpUxcOty',
            'name' => 'my-avatar.png',
            'size' => 90996,
            'type' => 'image/png',
            'error' => 0,
        ];

        $result = UploadedFiles::fromSuperGlobal()->toArray();

        // Assert
        $this->assertArrayHasKey('avatar', $result);
        $this->assertInstanceOf(UploadedFile::class, $result['avatar']);

        // Cleanup
        unset($_FILES['avatar']);
    }
}
