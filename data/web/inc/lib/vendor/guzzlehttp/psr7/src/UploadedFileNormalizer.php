<?php

declare(strict_types=1);

namespace GuzzleHttp\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\UploadedFileInterface;

/**
 * @internal
 *
 * @phpstan-type UploadedFileTree array<array-key, UploadedFileInterface|array>
 */
final class UploadedFileNormalizer
{
    private function __construct()
    {
    }

    /**
     * Return an UploadedFile instance array.
     *
     * @param array $files An array which respect $_FILES structure
     *
     * @return UploadedFileTree
     *
     * @throws InvalidArgumentException for unrecognized values
     */
    public static function normalize(array $files): array
    {
        $normalized = [];

        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                $normalized[$key] = $value;
            } elseif (is_array($value) && array_key_exists('tmp_name', $value)) {
                $normalized[$key] = self::createUploadedFileFromSpec($value);
            } elseif (is_array($value)) {
                $normalized[$key] = self::normalize($value);
                continue;
            } else {
                throw new InvalidArgumentException('Invalid value in files specification');
            }
        }

        return $normalized;
    }

    /**
     * Create and return an UploadedFile instance from a $_FILES specification.
     *
     * If the specification represents an array of values, this method will
     * delegate to normalizeNestedFileSpec() and return that return value.
     *
     * @param array $value $_FILES struct
     *
     * @return UploadedFileInterface|UploadedFileTree
     */
    private static function createUploadedFileFromSpec(array $value)
    {
        self::assertFileSpec($value);

        if (is_array($value['tmp_name'])) {
            return self::normalizeNestedFileSpec($value);
        }

        return new UploadedFile(
            $value['tmp_name'],
            Integers::assertNonNegativeInteger($value['size'], 'Uploaded file size'),
            Integers::assertNonNegativeInteger($value['error'], 'Uploaded file error'),
            $value['name'] ?? null,
            $value['type'] ?? null
        );
    }

    private static function assertFileSpec(array $value): void
    {
        if (!isset($value['tmp_name'], $value['size'], $value['error'])) {
            throw new InvalidArgumentException('Invalid file specification; expected keys "tmp_name", "size", and "error".');
        }
    }

    /**
     * Normalize an array of file specifications.
     *
     * Loops through all nested files and returns a normalized array of
     * UploadedFileInterface instances.
     *
     * @return UploadedFileTree
     */
    private static function normalizeNestedFileSpec(array $files = []): array
    {
        self::assertNestedFileSpec($files);

        $normalizedFiles = [];

        foreach (array_keys($files['tmp_name']) as $key) {
            if (!array_key_exists($key, $files['size']) || !array_key_exists($key, $files['error'])) {
                throw new InvalidArgumentException('Invalid nested file specification; expected "tmp_name", "size", and "error" arrays to have matching keys.');
            }

            $spec = [
                'tmp_name' => $files['tmp_name'][$key],
                'size' => $files['size'][$key],
                'error' => $files['error'][$key],
                'name' => $files['name'][$key] ?? null,
                'type' => $files['type'][$key] ?? null,
            ];
            $normalizedFiles[$key] = self::createUploadedFileFromSpec($spec);
        }

        return $normalizedFiles;
    }

    private static function assertNestedFileSpec(array $files): void
    {
        foreach (['tmp_name', 'size', 'error'] as $key) {
            if (!isset($files[$key]) || !is_array($files[$key])) {
                throw new InvalidArgumentException('Invalid nested file specification; expected keys "tmp_name", "size", and "error" to be arrays.');
            }
        }

        foreach (['name', 'type'] as $key) {
            if (isset($files[$key]) && !is_array($files[$key])) {
                throw new InvalidArgumentException(sprintf('Invalid nested file specification; expected key "%s" to be an array when present.', $key));
            }
        }
    }
}
