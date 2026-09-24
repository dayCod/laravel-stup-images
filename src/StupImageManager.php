<?php

declare(strict_types=1);

namespace Daycode\StupImage;

use Daycode\StupImage\Contracts\ImageProcessor;
use Daycode\StupImage\Contracts\Namer;
use Daycode\StupImage\Events\ImageDeleted;
use Daycode\StupImage\Events\ImageStored;
use Daycode\StupImage\Exceptions\StorageException;
use Daycode\StupImage\Exceptions\StupImageException;
use Daycode\StupImage\Naming\HashNamer;
use Daycode\StupImage\Naming\OriginalNamer;
use Daycode\StupImage\Naming\UlidNamer;
use Daycode\StupImage\Naming\UuidNamer;
use Illuminate\Contracts\Bus\Dispatcher as BusDispatcher;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use InvalidArgumentException;
use Throwable;

class StupImageManager
{
    /**
     * Manipulations that can be used as preset keys, in the order they are applied.
     */
    private const OPERATIONS = ['resize', 'cover', 'scale', 'contain'];

    /**
     * @var array<string, class-string<Namer>>
     */
    private const NAMERS = [
        'ulid' => UlidNamer::class,
        'uuid' => UuidNamer::class,
        'hash' => HashNamer::class,
        'original' => OriginalNamer::class,
    ];

    protected ?string $disk = null;

    public function __construct(protected Container $container) {}

    /**
     * Start an upload for a single file.
     */
    public function from(mixed $file): ImageUpload
    {
        return new ImageUpload($this, $file);
    }

    /**
     * Upload a file and delete the old path once the new image has been stored.
     */
    public function replace(?string $oldPath, mixed $file): ImageUpload
    {
        return $this->from($file)->replacing($oldPath);
    }

    /**
     * Start an upload for many files at once.
     *
     * @param  iterable<mixed>|null  $files
     */
    public function many(?iterable $files): ImageBatch
    {
        return new ImageBatch($this, $files ?? []);
    }

    /**
     * Use the given disk for the next operations.
     */
    public function disk(?string $disk): static
    {
        $manager = clone $this;
        $manager->disk = $disk;

        return $manager;
    }

    public function getDisk(): ?string
    {
        return $this->disk;
    }

    /**
     * Delete an image and its variants. Missing files are ignored.
     *
     * @throws StorageException
     */
    public function delete(?string $path): bool
    {
        if ($path === null || $path === '') {
            return false;
        }

        $diskName = $this->disk ?? $this->defaultDisk();
        $disk = $this->filesystem($diskName);

        $this->deleteVariants($disk, $diskName, $path);

        if (! $disk->exists($path)) {
            return false;
        }

        $this->deleteOrFail($disk, $diskName, $path);

        $this->deleted($diskName, $path);

        return true;
    }

    public function exists(?string $path): bool
    {
        return $path !== null && $path !== '' && $this->filesystem($this->disk ?? $this->defaultDisk())->exists($path);
    }

    /**
     * Get the URL of an image (or one of its variants), null when there is no path.
     */
    public function url(?string $path, ?string $variant = null): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if ($variant !== null) {
            $path = $this->variantPath($path, $variant);
        }

        return $this->filesystem($this->disk ?? $this->defaultDisk())->url($path);
    }

    /**
     * Get the path of a variant: "avatars/abc.jpg" + "thumb" => "avatars/abc/thumb.webp".
     */
    public function variantPath(string $path, string $variant): string
    {
        $info = pathinfo($path);
        $extension = $this->preset($variant)['format'] ?? $info['extension'] ?? 'jpg';
        $directory = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'].'/';

        return "{$directory}{$info['filename']}/{$variant}.{$extension}";
    }

    /**
     * Process the source image with each preset and write it next to the main image.
     * Written variants are removed again when one of them fails.
     *
     * @param  list<string>  $variants
     * @return array<string, StoredImage>
     *
     * @throws StupImageException
     */
    public function createVariants(string $diskName, string $path, string $source, array $variants, int $quality, ?string $visibility): array
    {
        $stored = [];

        try {
            foreach ($variants as $variant) {
                $preset = $this->preset($variant);
                $variantPath = $this->variantPath($path, $variant);

                $processed = $this->processor()->process(
                    $source,
                    $preset['manipulations'],
                    pathinfo($variantPath, PATHINFO_EXTENSION),
                    $preset['quality'] ?? $quality,
                );

                $this->write($diskName, $variantPath, $processed->contents, $visibility);

                $stored[$variant] = new StoredImage(
                    disk: $diskName,
                    path: $variantPath,
                    filename: basename($variantPath),
                    mimeType: $processed->mimeType,
                    size: $processed->size(),
                    width: $processed->width,
                    height: $processed->height,
                );
            }
        } catch (Throwable $e) {
            $disk = $this->filesystem($diskName);

            foreach ($stored as $image) {
                $disk->delete($image->path);
            }

            throw $e;
        }

        return $stored;
    }

    /**
     * Get a validated preset from the config.
     *
     * @return array{manipulations: list<array{0: string, 1: int|null, 2: int|null}>, format: string|null, quality: int|null}
     *
     * @throws InvalidArgumentException
     */
    public function preset(string $name): array
    {
        $preset = $this->rawConfig("presets.{$name}");

        if (! is_array($preset)) {
            throw new InvalidArgumentException("Image preset [{$name}] is not defined in config/stup-image.php.");
        }

        $manipulations = [];

        foreach (self::OPERATIONS as $operation) {
            if (isset($preset[$operation])) {
                $manipulations[] = [$operation, ...self::dimensions($preset[$operation], "presets.{$name}.{$operation}")];
            }
        }

        $format = $preset['format'] ?? null;
        $quality = $preset['quality'] ?? null;

        if ($format !== null && ! is_string($format)) {
            throw self::invalidConfig("presets.{$name}.format", 'a string or null');
        }

        if ($quality !== null && ! is_int($quality)) {
            throw self::invalidConfig("presets.{$name}.quality", 'an integer or null');
        }

        return [
            'manipulations' => $manipulations,
            'format' => $format === null ? null : MimeTypes::normalizeFormat($format),
            'quality' => $quality,
        ];
    }

    /**
     * @return list<string>
     */
    public function presetNames(): array
    {
        $presets = $this->rawConfig('presets', []);

        return is_array($presets) ? array_map(strval(...), array_keys($presets)) : [];
    }

    /**
     * Normalize a preset dimension value: [800, 600], [800, null], [800] or 800.
     *
     * @return array{0: int|null, 1: int|null}
     */
    private static function dimensions(mixed $value, string $key): array
    {
        $value = is_array($value) ? array_values($value) : [$value];

        foreach ([0, 1] as $index) {
            if (isset($value[$index]) && ! is_int($value[$index])) {
                throw self::invalidConfig($key, 'a width and height in pixels, e.g. [800, 600] or [800, null]');
            }
        }

        /** @var array{0?: int|null, 1?: int|null} $value */
        return [$value[0] ?? null, $value[1] ?? null];
    }

    /**
     * @internal
     *
     * @throws StorageException
     */
    public function write(string $diskName, string $path, string $contents, ?string $visibility): void
    {
        $options = $visibility === null ? [] : ['visibility' => $visibility];

        try {
            $written = $this->filesystem($diskName)->put($path, $contents, $options);
        } catch (Throwable $e) {
            throw StorageException::unableToWrite($diskName, $path, $e);
        }

        if ($written === false) {
            throw StorageException::unableToWrite($diskName, $path);
        }
    }

    /**
     * Delete an image and its variants without dispatching events. Errors are reported, not thrown.
     *
     * @internal
     */
    public function discard(StoredImage $image): void
    {
        try {
            $disk = $this->filesystem($image->disk);

            foreach ($image->variants as $variant) {
                $disk->delete($variant->path);
            }

            $disk->delete($image->path);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @internal
     */
    public function stored(StoredImage $image): void
    {
        $this->events()->dispatch(new ImageStored($image));
    }

    /**
     * @internal
     */
    public function deleted(string $disk, string $path): void
    {
        $this->events()->dispatch(new ImageDeleted($disk, $path));
    }

    /**
     * @internal
     */
    public function dispatch(object $job): void
    {
        $this->container->make(BusDispatcher::class)->dispatch($job);
    }

    public function processor(): ImageProcessor
    {
        return $this->container->make(ImageProcessor::class);
    }

    public function namer(): Namer
    {
        $naming = $this->configString('naming') ?? 'ulid';
        $class = self::NAMERS[$naming] ?? $naming;

        $namer = is_subclass_of($class, Namer::class) ? $this->container->make($class) : null;

        if (! $namer instanceof Namer) {
            throw new InvalidArgumentException("Invalid image naming strategy [{$naming}]. Use ulid, uuid, hash, original or a class implementing ".Namer::class.'.');
        }

        return $namer;
    }

    public function filesystem(string $disk): Filesystem
    {
        return $this->container->make(FilesystemFactory::class)->disk($disk);
    }

    public function defaultDisk(): string
    {
        return $this->configString('disk') ?? $this->repository()->string('filesystems.default', 'local');
    }

    /**
     * @throws InvalidArgumentException when the config value is not a string or null
     */
    public function configString(string $key): ?string
    {
        $value = $this->rawConfig($key);

        if ($value !== null && ! is_string($value)) {
            throw self::invalidConfig($key, 'a string or null');
        }

        return $value === '' ? null : $value;
    }

    /**
     * @throws InvalidArgumentException when the config value is not an integer or null
     */
    public function configInt(string $key): ?int
    {
        $value = $this->rawConfig($key);

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        if ($value !== null && ! is_int($value)) {
            throw self::invalidConfig($key, 'an integer or null');
        }

        return $value;
    }

    /**
     * @return list<string>|null
     *
     * @throws InvalidArgumentException when the config value is not a list of strings or null
     */
    public function configList(string $key): ?array
    {
        $value = $this->rawConfig($key);

        if ($value === null) {
            return null;
        }

        if (! is_array($value) || array_filter($value, is_string(...)) !== $value) {
            throw self::invalidConfig($key, 'an array of strings or null');
        }

        return array_values($value);
    }

    protected function rawConfig(string $key, mixed $default = null): mixed
    {
        return $this->repository()->get("stup-image.{$key}", $default);
    }

    protected function repository(): Repository
    {
        return $this->container->make(Repository::class);
    }

    private static function invalidConfig(string $key, string $expected): InvalidArgumentException
    {
        return new InvalidArgumentException("The config value [stup-image.{$key}] must be {$expected}.");
    }

    protected function events(): EventDispatcher
    {
        return $this->container->make(EventDispatcher::class);
    }

    /**
     * Delete the variants of every configured preset, then the variants folder when it is empty.
     * Only known variant paths are touched, never other files in that folder.
     */
    private function deleteVariants(Filesystem $disk, string $diskName, string $path): void
    {
        $presets = $this->presetNames();

        if ($presets === []) {
            return;
        }

        foreach ($presets as $variant) {
            $variantPath = $this->variantPath($path, $variant);

            if ($disk->exists($variantPath)) {
                $this->deleteOrFail($disk, $diskName, $variantPath);
            }
        }

        $directory = dirname($this->variantPath($path, $presets[0]));

        if ($disk->exists($directory) && $disk->allFiles($directory) === [] && $disk->allDirectories($directory) === []) {
            $disk->deleteDirectory($directory);
        }
    }

    private function deleteOrFail(Filesystem $disk, string $diskName, string $path): void
    {
        try {
            $deleted = $disk->delete($path);
        } catch (Throwable $e) {
            throw StorageException::unableToDelete($diskName, $path, $e);
        }

        if ($deleted === false) {
            throw StorageException::unableToDelete($diskName, $path);
        }
    }
}
