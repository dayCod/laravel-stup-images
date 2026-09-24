<?php

declare(strict_types=1);

namespace Daycode\StupImage;

use InvalidArgumentException;

/**
 * @internal
 */
final class MimeTypes
{
    /**
     * @var array<string, string>
     */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/pjpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'image/avif' => 'avif',
        'image/bmp' => 'bmp',
        'image/x-ms-bmp' => 'bmp',
        'image/tiff' => 'tif',
        'image/heic' => 'heic',
        'image/heif' => 'heic',
    ];

    /**
     * @var array<string, string>
     */
    private const FORMATS = [
        'jpg' => 'jpg',
        'jpeg' => 'jpg',
        'png' => 'png',
        'gif' => 'gif',
        'webp' => 'webp',
        'avif' => 'avif',
        'bmp' => 'bmp',
        'tif' => 'tif',
        'tiff' => 'tif',
        'heic' => 'heic',
    ];

    public static function extension(string $mimeType): ?string
    {
        return self::EXTENSIONS[strtolower($mimeType)] ?? null;
    }

    public static function isKnownExtension(string $extension): bool
    {
        return isset(self::FORMATS[strtolower($extension)]);
    }

    /**
     * Normalize a user supplied format (e.g. "JPEG") to a file extension (e.g. "jpg").
     *
     * @throws InvalidArgumentException
     */
    public static function normalizeFormat(string $format): string
    {
        return self::FORMATS[strtolower(ltrim($format, '.'))]
            ?? throw new InvalidArgumentException("Unsupported image format [{$format}].");
    }
}
