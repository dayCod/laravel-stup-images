<?php

declare(strict_types=1);

namespace Daycode\StupImage;

use Daycode\StupImage\Exceptions\DisallowedMimeTypeException;
use Daycode\StupImage\Exceptions\ImageTooLargeException;
use Daycode\StupImage\Exceptions\InvalidImageException;
use Daycode\StupImage\Jobs\GenerateImageVariants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Conditionable;
use InvalidArgumentException;
use Throwable;

/**
 * Fluent options shared by single (ImageUpload) and batch (ImageBatch) uploads.
 */
abstract class PendingUpload
{
    use Conditionable;

    protected ?string $disk = null;

    protected ?string $directory = null;

    /**
     * @var list<array{0: string, 1: int|null, 2: int|null}>
     */
    protected array $manipulations = [];

    protected ?string $format = null;

    protected ?int $quality = null;

    protected ?string $visibility = null;

    /**
     * @var list<string>
     */
    protected array $variants = [];

    protected bool $queueVariants = false;

    protected ?string $queueConnection = null;

    protected ?string $queueName = null;

    public function __construct(protected StupImageManager $manager)
    {
        $this->disk = $manager->getDisk();
    }

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function directory(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    /**
     * Resize to the exact dimensions (the aspect ratio is not kept).
     */
    public function resize(?int $width = null, ?int $height = null): static
    {
        return $this->manipulate('resize', $width, $height);
    }

    /**
     * Crop and resize to fill the given dimensions exactly.
     */
    public function cover(int $width, int $height): static
    {
        return $this->manipulate('cover', $width, $height);
    }

    /**
     * Resize while keeping the aspect ratio.
     */
    public function scale(?int $width = null, ?int $height = null): static
    {
        return $this->manipulate('scale', $width, $height);
    }

    /**
     * Fit the image inside the given dimensions and pad the remaining area.
     */
    public function contain(int $width, int $height): static
    {
        return $this->manipulate('contain', $width, $height);
    }

    public function format(string $format, ?int $quality = null): static
    {
        $this->format = MimeTypes::normalizeFormat($format);

        return $quality === null ? $this : $this->quality($quality);
    }

    public function toWebp(?int $quality = null): static
    {
        return $this->format('webp', $quality);
    }

    public function toJpeg(?int $quality = null): static
    {
        return $this->format('jpg', $quality);
    }

    public function toPng(): static
    {
        return $this->format('png');
    }

    public function toAvif(?int $quality = null): static
    {
        return $this->format('avif', $quality);
    }

    public function quality(int $quality): static
    {
        if ($quality < 1 || $quality > 100) {
            throw new InvalidArgumentException("The quality must be between 1 and 100, [{$quality}] given.");
        }

        $this->quality = $quality;

        return $this;
    }

    public function visibility(?string $visibility): static
    {
        $this->visibility = $visibility;

        return $this;
    }

    /**
     * Apply a preset from the "stup-image.presets" config.
     */
    public function preset(string $name): static
    {
        $preset = $this->manager->preset($name);

        foreach ($preset['manipulations'] as [$operation, $width, $height]) {
            $this->manipulate($operation, $width, $height);
        }

        if ($preset['format'] !== null) {
            $this->format($preset['format']);
        }

        if ($preset['quality'] !== null) {
            $this->quality($preset['quality']);
        }

        return $this;
    }

    /**
     * Also store the given presets as variants, e.g. "avatars/{name}/thumb.webp".
     *
     * @param  list<string>|string  $presets
     */
    public function variants(array|string $presets, string ...$more): static
    {
        $presets = is_array($presets) ? $presets : [$presets, ...$more];

        foreach ($presets as $preset) {
            $this->manager->preset($preset);
        }

        $this->variants = array_values(array_unique([...$this->variants, ...$presets]));

        return $this;
    }

    /**
     * Generate the variants on the queue instead of during the request.
     */
    public function queue(?string $queue = null, ?string $connection = null): static
    {
        $this->queueVariants = true;
        $this->queueName = $queue;
        $this->queueConnection = $connection;

        return $this;
    }

    protected function manipulate(string $operation, ?int $width, ?int $height): static
    {
        if ($width === null && $height === null) {
            throw new InvalidArgumentException("The [{$operation}] operation needs a width and/or a height.");
        }

        $this->manipulations[] = [$operation, $width, $height];

        return $this;
    }

    /**
     * Validate, process and write a single file.
     *
     * When $announce is false the caller is responsible for calling $manager->stored().
     *
     * @param  (callable(UploadedFile, string, string, \Illuminate\Contracts\Filesystem\Filesystem): string)|null  $namer
     */
    protected function storeFile(mixed $file, ?callable $namer = null, bool $announce = true): StoredImage
    {
        $file = $this->validate($file);
        $contents = $this->contents($file);

        $processed = $this->manager->processor()->process(
            $contents,
            $this->manipulations,
            $this->format ?? $this->manager->configString('default_format'),
            $this->resolvedQuality(),
        );

        $diskName = $this->resolvedDisk();
        $disk = $this->manager->filesystem($diskName);
        $directory = $this->resolvedDirectory();

        $filename = $namer !== null
            ? $namer($file, $processed->extension, $directory, $disk)
            : $this->manager->namer()->name($file, $processed->extension, $directory, $disk);

        $path = ltrim("{$directory}/{$filename}", '/');
        $visibility = $this->resolvedVisibility();

        $this->manager->write($diskName, $path, $processed->contents, $visibility);

        $variants = [];

        if ($this->variants !== [] && ! $this->queueVariants) {
            try {
                $variants = $this->manager->createVariants($diskName, $path, $contents, $this->variants, $this->resolvedQuality(), $visibility);
            } catch (Throwable $e) {
                $this->manager->filesystem($diskName)->delete($path);

                throw $e;
            }
        }

        $image = new StoredImage(
            disk: $diskName,
            path: $path,
            filename: $filename,
            mimeType: $processed->mimeType,
            size: $processed->size(),
            width: $processed->width,
            height: $processed->height,
            variants: $variants,
        );

        if ($this->variants !== [] && $this->queueVariants) {
            $this->manager->dispatch(
                (new GenerateImageVariants($diskName, $path, $this->variants, $this->resolvedQuality(), $visibility))
                    ->onConnection($this->queueConnection)
                    ->onQueue($this->queueName),
            );
        }

        if ($announce) {
            $this->manager->stored($image);
        }

        return $image;
    }

    protected function validate(mixed $file): UploadedFile
    {
        if (! $file instanceof UploadedFile) {
            throw InvalidImageException::notAnUploadedFile($file);
        }

        $name = $file->getClientOriginalName();

        if (! $file->isValid()) {
            throw InvalidImageException::invalidUpload($name, $file->getErrorMessage());
        }

        $maxSize = $this->manager->configInt('max_size');
        $size = (int) ceil(((int) $file->getSize()) / 1024);

        if ($maxSize !== null && $size > $maxSize) {
            throw ImageTooLargeException::fileSize($name, $size, $maxSize);
        }

        $allowed = $this->manager->configList('allowed_mime_types');
        $mimeType = strtolower((string) $file->getMimeType());

        if (! empty($allowed) && ! in_array($mimeType, array_map('strtolower', $allowed), true)) {
            throw DisallowedMimeTypeException::make($name, $mimeType, $allowed);
        }

        return $file;
    }

    protected function contents(UploadedFile $file): string
    {
        $name = $file->getClientOriginalName();
        $contents = $file->get();

        if ($contents === false || $contents === '') {
            throw InvalidImageException::unreadable($name);
        }

        $maxWidth = $this->manager->configInt('max_width');
        $maxHeight = $this->manager->configInt('max_height');

        if ($maxWidth !== null || $maxHeight !== null) {
            $size = @getimagesizefromstring($contents);

            if ($size === false) {
                throw InvalidImageException::unreadable($name);
            }

            [$width, $height] = $size;

            if (($maxWidth !== null && $width > $maxWidth) || ($maxHeight !== null && $height > $maxHeight)) {
                throw ImageTooLargeException::dimensions($name, $width, $height, $maxWidth, $maxHeight);
            }
        }

        return $contents;
    }

    protected function resolvedDisk(): string
    {
        return $this->disk ?? $this->manager->defaultDisk();
    }

    protected function resolvedDirectory(): string
    {
        return trim($this->directory ?? $this->manager->configString('directory') ?? '', '/');
    }

    protected function resolvedQuality(): int
    {
        return $this->quality ?? $this->manager->configInt('quality') ?? 85;
    }

    protected function resolvedVisibility(): ?string
    {
        return $this->visibility ?? $this->manager->configString('visibility');
    }

    /**
     * Sanitize an explicit name given through ->name().
     */
    protected static function sanitizeName(string $name): string
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);

        if ($extension !== '' && MimeTypes::isKnownExtension($extension)) {
            $name = pathinfo($name, PATHINFO_FILENAME);
        }

        $name = Str::slug($name);

        if ($name === '') {
            throw new InvalidArgumentException('The image name must contain at least one alphanumeric character.');
        }

        return $name;
    }
}
