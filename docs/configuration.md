# Configuration

Publish the file with `php artisan stup-image:install` or `php artisan vendor:publish --tag=stup-image-config`.

| Key | Default | Description |
|---|---|---|
| `disk` | `env('STUP_IMAGE_DISK')` | Disk to store images on. `null` uses `filesystems.default`. |
| `directory` | `'images'` | Default directory inside the disk. |
| `driver` | `env('STUP_IMAGE_DRIVER', 'gd')` | `gd` or `imagick`. |
| `visibility` | `env('STUP_IMAGE_VISIBILITY')` | `public`, `private` or `null` (the disk's own setting). New S3 buckets have ACLs disabled, and uploads that set a visibility fail on them. |
| `naming` | `'ulid'` | `ulid`, `uuid`, `hash`, `original` or the class name of a `Namer`. |
| `allowed_mime_types` | JPEG, PNG, GIF, WebP, AVIF | Detected from the file contents. `null` accepts anything the driver can decode. |
| `max_size` | `10240` | Maximum upload size in KB. `null` means no limit. |
| `max_width` / `max_height` | `null` | Maximum source dimensions in pixels. `null` means no limit. |
| `default_format` | `null` | Convert every image, e.g. `'webp'`. `null` keeps the original format. |
| `quality` | `85` | Quality (1–100) for lossy formats. |
| `presets` | `thumbnail`, `medium` | Named option sets for `->preset()` and `->variants()`. |

Invalid values, such as `max_size: 'big'`, throw an `InvalidArgumentException` that names the config key. Integer options also accept numeric strings, so values from `env()` work.

## Naming strategies

| Strategy | Example | Notes |
|---|---|---|
| `ulid` | `01j9zk3v7c8e2x4m6n8p0q2r4s.jpg` | Default. Unique and sortable by time. |
| `uuid` | `0192d5a4-7b3c-7d2e-9f1a-3b5c7d9e1f2a.jpg` | UUIDv7. |
| `hash` | `9f86d081884c7d659a2feaa0c55ad015.jpg` | 128 random bits. |
| `original` | `my-photo.jpg`, `my-photo-1.jpg` | Slug of the client filename, deduplicated. The base name is unique across extensions. |

A custom strategy:

```php
use Daycode\StupImage\Contracts\Namer;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class DateNamer implements Namer
{
    public function name(UploadedFile $file, string $extension, string $directory, Filesystem $disk): string
    {
        return now()->format('Y/m/d/').strtolower((string) Str::ulid()).'.'.$extension;
    }
}
```

```php
'naming' => App\Support\DateNamer::class,
```

## Presets

```php
'presets' => [
    'thumbnail' => ['cover' => [150, 150], 'format' => 'webp', 'quality' => 80],
    'medium'    => ['scale' => [800, null]],
    'banner'    => ['cover' => [1600, 400], 'format' => 'avif'],
],
```

| Key | Value |
|---|---|
| `resize`, `cover`, `scale`, `contain` | `[width, height]`. Use `null` for one side with `resize`/`scale`, e.g. `[800, null]`. A single integer means the width. |
| `format` | `jpg`, `png`, `gif`, `webp`, `avif`, ... |
| `quality` | 1–100 |

Operations in a preset are applied in the order `resize`, `cover`, `scale`, `contain`.

Deleting or replacing an image also deletes its variant for **every configured preset**. If you remove a preset from the config, variants stored for it are no longer cleaned up.
