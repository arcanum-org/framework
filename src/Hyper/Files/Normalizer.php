<?php

declare(strict_types=1);

namespace Arcanum\Hyper\Files;

use Psr\Http\Message\UploadedFileInterface;

final class Normalizer
{
    /**
     * @param string|array<string|int,mixed> $tmpName
     * @param int|null|array<string|int,mixed> $error
     * @param int|null|array<string|int,mixed> $size
     * @param string|null|array<string|int,mixed> $clientFilename
     * @param string|null|array<string|int,mixed> $clientMediaType
     * @return UploadedFileInterface|array<string, mixed>
     */
    public static function fromSpec(
        string|array $tmpName,
        int|null|array $error = null,
        int|null|array $size = null,
        string|null|array $clientFilename = null,
        string|null|array $clientMediaType = null
    ): UploadedFileInterface|array {
        if (static::containsArray($tmpName, $error, $size, $clientFilename, $clientMediaType)) {
            return static::normalizeSpec([
                'tmp_name' => $tmpName,
                'error' => $error,
                'size' => $size,
                'name' => $clientFilename,
                'type' => $clientMediaType,
            ]);
        }

        $error = Error::from((int)($error ?? \UPLOAD_ERR_OK));

        /** @var string $tmpName */
        /** @var int|null $size */
        /** @var string|null $clientFilename */
        /** @var string|null $clientMediaType */
        return new UploadedFile($tmpName, 'r+b', $error, $size, $clientFilename, $clientMediaType);
    }

    /**
     * Check if any of the given parameters is an array.
     *
     * @param mixed ...$params
     */
    protected static function containsArray(mixed ...$params): bool
    {
        foreach ($params as $param) {
            if (is_array($param)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $spec
     * @return array<string, mixed>
     */
    protected static function normalizeSpec(array $spec): array
    {
        $normalized = [];

        $tmpNames = $spec['tmp_name'] ?? [];
        if (!is_array($tmpNames)) {
            return $normalized;
        }

        $sizeData = $spec['size'] ?? null;
        $errorData = $spec['error'] ?? null;
        $nameData = $spec['name'] ?? null;
        $typeData = $spec['type'] ?? null;

        $sizes = is_array($sizeData) ? $sizeData : [];
        $errors = is_array($errorData) ? $errorData : [];
        $names = is_array($nameData) ? $nameData : [];
        $types = is_array($typeData) ? $typeData : [];

        foreach (array_keys($tmpNames) as $key) {
            $tmpNameVal = $tmpNames[$key];
            if (!is_string($tmpNameVal) && !is_array($tmpNameVal)) {
                continue;
            }

            $sizeVal = $sizes[$key] ?? null;
            $errorVal = $errors[$key] ?? null;
            $nameVal = $names[$key] ?? null;
            $typeVal = $types[$key] ?? null;

            $normalized[$key] = static::fromSpec(
                tmpName: $tmpNameVal,
                size: is_int($sizeVal) || is_array($sizeVal) ? $sizeVal : null,
                error: is_int($errorVal) || is_array($errorVal) ? $errorVal : null,
                clientFilename: is_string($nameVal) || is_array($nameVal) ? $nameVal : null,
                clientMediaType: is_string($typeVal) || is_array($typeVal) ? $typeVal : null,
            );
        }

        return $normalized;
    }
}
