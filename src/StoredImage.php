<?php

declare(strict_types=1);

namespace Daycode\StupImage;

use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Storage;
use JsonSerializable;
use Stringable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class StoredImage implements Arrayable, JsonSerializable, Stringable
{
    /**
     * @param  array<string, StoredImage>  $variants
     */
    public function __construct(
        public string $disk,
        public string $path,
        public string $filename,
        public string $mimeType,
        public int $size,
        public int $width,
        public int $height,
        public array $variants = [],
    ) {}

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function temporaryUrl(DateTimeInterface $expiration): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->path, $expiration);
    }

    public function exists(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }

    /**
     * Delete the image (and its variants) from its disk.
     */
    public function delete(): bool
    {
        return app(StupImageManager::class)->disk($this->disk)->delete($this->path);
    }

    public function variant(string $name): ?self
    {
        return $this->variants[$name] ?? null;
    }

    /**
     * @return array{disk: string, path: string, filename: string, mime_type: string, size: int, width: int, height: int, variants: array<string, array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'disk' => $this->disk,
            'path' => $this->path,
            'filename' => $this->filename,
            'mime_type' => $this->mimeType,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'variants' => array_map(fn (self $variant): array => $variant->toArray(), $this->variants),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->path;
    }
}
