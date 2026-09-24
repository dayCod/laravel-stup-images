# Laravel Stup Image

**Image uploads for Laravel without tables or migrations. One statement to validate, resize, convert and store an image on any disk.**

[![Tests](https://img.shields.io/github/actions/workflow/status/dayCod/laravel-stup-images/tests.yml?branch=master&label=tests)](https://github.com/dayCod/laravel-stup-images/actions/workflows/tests.yml)
[![Coverage](https://img.shields.io/codecov/c/github/dayCod/laravel-stup-images)](https://codecov.io/gh/dayCod/laravel-stup-images)
[![PHP](https://img.shields.io/packagist/dependency-v/daycode/stup-images/php)](https://packagist.org/packages/daycode/stup-images)
[![Laravel](https://img.shields.io/badge/laravel-12.x%20%7C%2013.x-red)](https://laravel.com)
[![Downloads](https://img.shields.io/packagist/dt/daycode/stup-images)](https://packagist.org/packages/daycode/stup-images)
[![License](https://img.shields.io/packagist/l/daycode/stup-images)](LICENSE)

```php
$path = StupImage::from($request->file('avatar'))->cover(400, 400)->toWebp()->store()->path;
```

That single statement checks the real MIME type, resizes, converts to WebP, gives the file a unique name, writes it to your disk (local, public, S3, ...) and returns the path to save in your database. You don't need a media table, a migration or any setup.

---

## Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Usage](#usage)
  - [Storing an image](#storing-an-image)
  - [Resizing](#resizing)
  - [Formats and quality](#formats-and-quality)
  - [Disks, directories, names and visibility](#disks-directories-names-and-visibility)
  - [Replacing an image](#replacing-an-image)
  - [Multiple images](#multiple-images)
  - [Deleting and URLs](#deleting-and-urls)
  - [Presets](#presets)
  - [Variants](#variants)
  - [Eloquent integration](#eloquent-integration)
  - [UploadedFile macro](#uploadedfile-macro)
  - [Events](#events)
  - [Error handling](#error-handling)
- [Testing](#testing)
- [Configuration](#configuration)
- [Stup Image or Spatie Media Library?](#stup-image-or-spatie-media-library)
- [Upgrading from v1](#upgrading-from-v1)
- [Security](#security)
- [Contributing](#contributing)
- [Credits and license](#credits-and-license)

---

## Features

- **One fluent statement** covers validation, processing and storage.
- **Any disk**: images are encoded in memory and written with `Storage::disk()->put()`, so local, `public`, S3, R2, Spaces and other disks all work.
- **Safe validation**: the MIME type is detected from the file contents, not from the client extension. There are limits for file size and dimensions.
- **Resize**: `cover`, `scale`, `contain` and `resize`, powered by [Intervention Image](https://image.intervention.io) with GD or Imagick.
- **Convert** to WebP, AVIF, JPEG or PNG and set the quality.
- **Unique names** by default (ULID). UUID, random hash, the slugged original name or your own namer are also available.
- **Safe replace**: the old image is deleted only after the new one has been stored.
- **Batches** are all-or-nothing: if one file fails, the files already stored are removed again.
- **Presets and variants**: define named sizes once, then store extra sizes such as `thumb` and `medium` next to the image, either during the request or on the queue.
- **Eloquent trait**: assign an `UploadedFile` to an attribute. The file is stored, and the old one is deleted after the model saves.
- **`StupImage::fake()`** with assertions for your own tests.
- **`ImageStored` / `ImageDeleted`** events.

## Requirements

| Stup Image | PHP | Laravel | Intervention Image |
|---|---|---|---|
| 2.x | 8.2 – 8.5 | 12.x, 13.x (13.x needs PHP 8.3+) | 3.11+ |
| 1.x | not declared | not declared | 3.11.2 |

You also need the PHP `gd` or `imagick` extension.

## Installation

```bash
composer require daycode/stup-images
```

The service provider and the `StupImage` facade are auto-discovered. You can optionally publish the config and check your image driver:

```bash
php artisan stup-image:install
```

If you store images on the `public` disk, link the storage folder once:

```bash
php artisan storage:link
```

## Quick start

```php
use Daycode\StupImage\Facades\StupImage;

public function update(Request $request)
{
    $request->validate(['avatar' => ['required', 'image', 'max:5120']]);

    $image = StupImage::replace($request->user()->avatar, $request->file('avatar'))
        ->directory('avatars')
        ->cover(400, 400)
        ->toWebp(quality: 80)
        ->store();

    $request->user()->update(['avatar' => $image->path]);

    return back();
}
```

> Store the **path** (`$image->path`, for example `avatars/01j9zk3v7c8e2x4m6n8p0q2r4s.webp`) in your database. With the path you can build URLs, delete or replace the image later without remembering the directory.

## Usage

### Storing an image

```php
$image = StupImage::from($request->file('photo'))->store();

$image->path;      // images/01j9zk3v7c8e2x4m6n8p0q2r4s.jpg
$image->filename;  // 01j9zk3v7c8e2x4m6n8p0q2r4s.jpg
$image->disk;      // public
$image->mimeType;  // image/jpeg
$image->size;      // bytes
$image->width;     // pixels
$image->height;    // pixels

$image->url();
$image->temporaryUrl(now()->addMinutes(5)); // on disks that support it (e.g. S3)
$image->exists();
$image->delete();

(string) $image;   // the path
$image->toArray(); // also JSON serializable
```

### Resizing

```php
StupImage::from($file)->cover(400, 400);   // crop and resize to exactly 400×400
StupImage::from($file)->scale(width: 1200); // resize, keeping the aspect ratio
StupImage::from($file)->contain(800, 800); // fit inside 800×800 and pad the rest
StupImage::from($file)->resize(800, 600);  // exactly 800×600, ignores the aspect ratio
```

Operations run in the order you call them. Use `when()` to apply one conditionally:

```php
StupImage::from($file)
    ->when($request->boolean('square'), fn ($upload) => $upload->cover(600, 600))
    ->store();
```

### Formats and quality

The original format is kept unless you convert it:

```php
->toWebp(quality: 80)
->toAvif(quality: 60)   // needs a GD/Imagick build with AVIF support
->toJpeg(quality: 85)
->toPng()
->format('webp')        // same as toWebp()
->quality(75)
```

You can also set `default_format` in the config to convert every upload, for example to WebP.

### Disks, directories, names and visibility

```php
StupImage::from($file)
    ->disk('s3')                 // default: config('stup-image.disk') ?? the default filesystem disk
    ->directory('users/avatars') // default: config('stup-image.directory')
    ->name('john-doe')           // optional; replaces the naming strategy and overwrites an existing file
    ->visibility('public')       // optional; default: the disk's own setting
    ->store();

// Choosing the disk on the facade works too:
StupImage::disk('s3')->from($file)->store();
```

### Replacing an image

```php
$image = StupImage::replace($user->avatar, $request->file('avatar'))
    ->directory('avatars')
    ->store();
```

The old image, including its variants, is deleted **only after** the new image has been stored. If the new upload fails, the old one stays. `$user->avatar` may be `null`.

### Multiple images

```php
$images = StupImage::many($request->file('gallery'))
    ->directory('gallery')
    ->scale(width: 1200)
    ->store(); // Collection<StoredImage>

$post->photos()->createMany($images->map(fn ($image) => ['path' => $image->path]));
```

If any file fails, the files already stored are removed again and the exception is rethrown.

### Deleting and URLs

```php
StupImage::delete($user->avatar);             // default disk; also deletes its variants
StupImage::disk('s3')->delete($user->avatar);

StupImage::url($user->avatar);                // null when the path is null/empty
StupImage::url($user->avatar, 'thumbnail');   // URL of a variant
StupImage::exists($user->avatar);
```

Deleting a missing file doesn't throw. `delete()` returns `false` in that case.

### Presets

Define named option sets in `config/stup-image.php`:

```php
'presets' => [
    'thumbnail' => ['cover' => [150, 150], 'format' => 'webp', 'quality' => 80],
    'medium'    => ['scale' => [800, null]],
],
```

```php
StupImage::from($file)->preset('thumbnail')->store();
```

Presets support the keys `resize`, `cover`, `scale`, `contain` (as `[width, height]`), `format` and `quality`. Options you call after `preset()` override the preset.

### Variants

Store extra sizes next to the image in one go:

```php
$image = StupImage::from($file)
    ->directory('avatars')
    ->variants(['thumbnail', 'medium'])
    ->store();

$image->path;                          // avatars/01j9zk....jpg
$image->variant('thumbnail')->path;    // avatars/01j9zk.../thumbnail.webp
$image->variant('medium')->url();

// Later, from the stored path only:
StupImage::url($user->avatar, 'thumbnail');
StupImage::variantPath($user->avatar, 'thumbnail');
```

Variants are generated from the original upload. A variant uses its preset's `format`, or the image's format when the preset has none. `delete()` and `replace()` remove the variants of every configured preset too.

To generate the variants on the queue instead of during the request, add `queue()`:

```php
StupImage::from($file)->variants(['thumbnail', 'medium'])->queue()->store();
StupImage::from($file)->variants(['thumbnail'])->queue('images', 'redis')->store(); // queue, connection
```

Queued variants are generated from the **stored** image, so apply size-reducing options only through variants when you queue them.

### Eloquent integration

```php
use Daycode\StupImage\Concerns\HasStupImages;

class User extends Model
{
    use HasStupImages;

    protected array $stupImages = [
        'avatar' => 'avatars', // the directory
        'cover'  => ['directory' => 'covers', 'disk' => 's3', 'preset' => 'medium', 'variants' => ['thumbnail']],
    ];
}
```

```php
$user->update(['avatar' => $request->file('avatar')]); // stores the image, then deletes the old one after saving
$user->update(['avatar' => null]);                     // deletes the image after saving

$user->avatarUrl();             // or $user->stupImageUrl('avatar')
$user->coverUrl('thumbnail');   // URL of a variant
```

- The column stores the path, so you still need a nullable string column. No extra tables are involved.
- Old images are deleted only **after** the model has been saved.
- Deleting the model deletes its images. With `SoftDeletes`, they're deleted on `forceDelete()`.
- A failed upload aborts the save and removes any image stored by that save.

### UploadedFile macro

```php
$image = $request->file('avatar')->storeImage('avatars');       // directory
$image = $request->file('avatar')->storeImage('avatars', 's3'); // directory, disk
```

### Events

| Event | Payload | When |
|---|---|---|
| `Daycode\StupImage\Events\ImageStored` | `$event->image` (`StoredImage`) | After an image (with its variants) is stored. Batches dispatch after all files succeed. Queued variants dispatch one event each. |
| `Daycode\StupImage\Events\ImageDeleted` | `$event->disk`, `$event->path` | After an existing image is deleted. |

### Error handling

Every failure throws a subclass of `Daycode\StupImage\Exceptions\StupImageException`:

| Exception | When |
|---|---|
| `InvalidImageException` | Not an `UploadedFile`, a failed upload, or contents that can't be decoded as an image |
| `DisallowedMimeTypeException` | The detected MIME type isn't in `allowed_mime_types` |
| `ImageTooLargeException` | Over `max_size`, `max_width` or `max_height` |
| `StorageException` | The disk failed to write, read or delete |
| `DriverNotAvailableException` | The configured `gd`/`imagick` driver is unknown or not installed |

A wrong configuration or argument (unknown preset, invalid quality, ...) throws an `InvalidArgumentException`.

```php
try {
    $image = StupImage::from($request->file('avatar'))->store();
} catch (DisallowedMimeTypeException|ImageTooLargeException $e) {
    return back()->withErrors(['avatar' => $e->getMessage()]);
}
```

> You should still validate requests with Laravel's `image`, `mimes` and `max` rules so users get friendly messages. Stup Image's checks are the safety net behind them.

## Testing

`StupImage::fake()` fakes every disk the package uses. Images are still processed for real, so dimensions and formats are accurate, and every operation is recorded:

```php
use Daycode\StupImage\Facades\StupImage;

it('updates the avatar', function () {
    $fake = StupImage::fake();
    $user = User::factory()->create(['avatar' => 'avatars/old.jpg']);

    $this->actingAs($user)->put('/profile', [
        'avatar' => UploadedFile::fake()->image('me.jpg', 800, 800),
    ]);

    $fake->assertStored($user->fresh()->avatar)
        ->assertStored(fn ($image) => $image->width === 400)
        ->assertStoredCount(1)
        ->assertDeleted('avatars/old.jpg');
});
```

Available assertions: `assertStored(?path|callback, ?times)`, `assertStoredCount()`, `assertNothingStored()`, `assertDeleted()`, `assertNotDeleted()` and `assertNothingDeleted()`. You can also read the records with `storedImages()` and `deletedImages()`.

## Configuration

```php
return [
    'disk' => env('STUP_IMAGE_DISK'),               // null = filesystems.default
    'directory' => 'images',
    'driver' => env('STUP_IMAGE_DRIVER', 'gd'),     // gd | imagick
    'visibility' => env('STUP_IMAGE_VISIBILITY'),   // null = the disk's own setting
    'naming' => 'ulid',                             // ulid | uuid | hash | original | a Namer class
    'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'],
    'max_size' => 10 * 1024,                        // KB, null = no limit
    'max_width' => null,                            // px, null = no limit
    'max_height' => null,                           // px, null = no limit
    'default_format' => null,                       // null = keep the original format
    'quality' => 85,
    'presets' => [/* ... */],
];
```

About `visibility`: new S3 buckets have ACLs disabled, and setting a visibility makes uploads to them fail. That's why the default is `null`.

About `original` naming: the client filename is slugged (`My Photo.JPG` → `my-photo.jpg`) and deduplicated (`my-photo-1.jpg`).

For a custom naming strategy, implement `Daycode\StupImage\Contracts\Namer` and set its class name in `naming`.

## Stup Image or Spatie Media Library?

[spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary) is an excellent, far more complete package. Here's a comparison to help you pick:

| | Stup Image | Spatie Media Library |
|---|---|---|
| Database | Your own string column | A `media` table (migration) |
| Setup | `composer require` | Migration, model interface and trait, config |
| Files | Images only | Any file type |
| Conversions | Presets and variants (sync or queued) | Conversions, responsive images |
| Multiple files per model | Your own relation or JSON column | Built-in collections |
| Custom properties, ordering, downloads | — | ✅ |
| Learning curve | Minutes | Hours |

**Pick Stup Image** when you want an avatar, cover or product image stored with a path in an existing column, and nothing more.
**Pick Spatie Media Library** when you manage many files per model, non-image files, custom properties or responsive images.

## Upgrading from v1

v2 is a rewrite, but the v1 `Stupable` trait still works as a deprecated wrapper, so you can upgrade gradually. See [UPGRADE.md](UPGRADE.md) for the breaking changes and the v1 → v2 mapping.

## Security

- The MIME type is detected from the file contents (`finfo`). A file renamed to `.jpg` is rejected.
- Only the bytes of the upload are decoded, never a path, and only the default image MIME types are allowed. SVG is not allowed.
- Filenames are generated (ULID) by default, so client filenames never reach your disk.
- For untrusted uploads, consider setting `max_width`/`max_height`. Decoding a very large image uses a lot of memory.

If you discover a security vulnerability, please follow [SECURITY.md](SECURITY.md).

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md). Before you open a PR, run these:

```bash
composer test      # Pest
composer analyse   # Larastan (level max)
composer format    # Pint
```

See [CHANGELOG.md](CHANGELOG.md) for what's new.

## Credits and license

- **Author:** [Wirandra Alaya (dayCod)](https://github.com/dayCod)
- **Image processing:** [Intervention Image](https://github.com/Intervention/image)
- [All contributors](https://github.com/dayCod/laravel-stup-images/graphs/contributors)

Stup Image is open-source software licensed under the [MIT license](LICENSE). If it saves you time, please give it a ⭐ on [GitHub](https://github.com/dayCod/laravel-stup-images).
