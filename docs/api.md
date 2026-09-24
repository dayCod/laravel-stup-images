# API reference

All entry points live on the `Daycode\StupImage\Facades\StupImage` facade, which resolves `Daycode\StupImage\StupImageManager`.

## Manager

| Method | Returns | Description |
|---|---|---|
| `from(UploadedFile $file)` | `ImageUpload` | Start a single upload. |
| `replace(?string $oldPath, UploadedFile $file)` | `ImageUpload` | Upload, then delete `$oldPath` (and its variants) once the new image is stored. |
| `many(?iterable $files)` | `ImageBatch` | Start an all-or-nothing batch upload. |
| `disk(?string $disk)` | `StupImageManager` | A copy of the manager that uses `$disk` for `from`, `replace`, `many`, `delete`, `url` and `exists`. |
| `delete(?string $path)` | `bool` | Delete an image and its variants. Returns `false` when the path is empty or missing. |
| `exists(?string $path)` | `bool` | Whether the image exists. |
| `url(?string $path, ?string $variant = null)` | `?string` | The URL of the image or one of its variants. Returns `null` for an empty path. |
| `variantPath(string $path, string $variant)` | `string` | `avatars/abc.jpg` + `thumbnail` → `avatars/abc/thumbnail.webp`. |
| `preset(string $name)` | `array` | The validated preset. Throws for unknown presets. |
| `presetNames()` | `list<string>` | The configured preset names. |
| `fake()` | `StupImageFake` | Swap in the testing fake. See [Testing](testing.md). |

## Upload options (`ImageUpload` and `ImageBatch`)

Every option returns the builder, so calls can be chained. The builders use Laravel's `Conditionable` trait (`when()` and `unless()`).

| Method | Description |
|---|---|
| `disk(string $disk)` | Disk to store on. |
| `directory(string $directory)` | Directory inside the disk. Slashes are trimmed, and `''` or `'/'` means the root. |
| `cover(int $width, int $height)` | Crop and resize to fill exactly. |
| `scale(?int $width = null, ?int $height = null)` | Resize while keeping the aspect ratio. |
| `contain(int $width, int $height)` | Fit inside the box and pad the rest. |
| `resize(?int $width = null, ?int $height = null)` | Resize to exact dimensions (distorts the image). |
| `format(string $format, ?int $quality = null)` | Output format: `jpg`/`jpeg`, `png`, `gif`, `webp`, `avif`, `bmp`, `tif`, `heic`. AVIF, TIFF and HEIC depend on the driver (GD can't encode TIFF or HEIC). |
| `toWebp(?int $quality)`, `toAvif(?int $quality)`, `toJpeg(?int $quality)`, `toPng()` | Format shortcuts. |
| `quality(int $quality)` | 1–100. |
| `visibility(?string $visibility)` | `public` or `private`. `null` uses the disk's own setting. |
| `preset(string $name)` | Apply a config preset. Options called later override it. |
| `variants(array\|string ...$presets)` | Also store these presets as `{dir}/{name}/{preset}.{ext}`. |
| `queue(?string $queue = null, ?string $connection = null)` | Generate the variants on the queue, from the stored image. |

`ImageUpload` only:

| Method | Description |
|---|---|
| `name(string $name)` | Explicit filename. It's slugged, and a known image extension is stripped. An existing file with the same name is **overwritten**. |
| `replacing(?string $path)` | The path to delete after a successful store (what `replace()` uses). |
| `store()` | Returns a `StoredImage`. |

`ImageBatch::store()` returns a `Collection<int, StoredImage>`.

## `StoredImage`

A readonly value object.

| Member | Description |
|---|---|
| `$disk`, `$path`, `$filename`, `$mimeType`, `$size`, `$width`, `$height` | Details of the stored file. |
| `$variants` | `array<string, StoredImage>`. Empty when variants are queued. |
| `variant(string $name)` | `?StoredImage` |
| `url()`, `temporaryUrl(DateTimeInterface $expiration)` | URLs from the disk. |
| `exists()`, `delete()` | `delete()` goes through the manager, so it removes the variants and dispatches `ImageDeleted`. |
| `toArray()`, `jsonSerialize()`, `__toString()` | `__toString()` returns the path. |

## `UploadedFile::storeImage(?string $directory = null, ?string $disk = null)`

A macro that does the same as `StupImage::from($file)->directory($directory)->disk($disk)->store()`.

## Events

- `Daycode\StupImage\Events\ImageStored` (`public StoredImage $image`)
- `Daycode\StupImage\Events\ImageDeleted` (`public string $disk`, `public string $path`)

## Exceptions

All of them extend `Daycode\StupImage\Exceptions\StupImageException`, which extends `RuntimeException`.

| Exception | Thrown when |
|---|---|
| `InvalidImageException` | Not an `UploadedFile`, the upload failed, or the contents can't be decoded |
| `DisallowedMimeTypeException` | The detected MIME type isn't allowed |
| `ImageTooLargeException` | Over `max_size`, `max_width` or `max_height` |
| `StorageException` | The disk failed to write, read or delete |
| `DriverNotAvailableException` | The driver is unknown or its PHP extension is missing |

Invalid arguments and invalid configuration throw `InvalidArgumentException`.

## Extension points

- `Daycode\StupImage\Contracts\Namer`: custom filenames (see [Configuration](configuration.md#naming-strategies)).
- `Daycode\StupImage\Contracts\ImageProcessor`: replace the image processing, for example with a remote service. Bind your implementation in a service provider:

  ```php
  $this->app->bind(ImageProcessor::class, MyProcessor::class);
  ```
