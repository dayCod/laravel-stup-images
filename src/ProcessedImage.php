<?php

declare(strict_types=1);

namespace Daycode\StupImage;

/**
 * An encoded image that is ready to be written to a disk.
 */
final readonly class ProcessedImage
{
    public function __construct(
        public string $contents,
        public string $mimeType,
        public string $extension,
        public int $width,
        public int $height,
    ) {}

    public function size(): int
    {
        return strlen($this->contents);
    }
}
