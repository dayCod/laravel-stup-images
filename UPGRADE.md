# Upgrade Guide

## Upgrading from 1.x to 2.0

v2 is a rewrite focused on disk-agnostic storage, safe validation and a fluent API. The v1 `Stupable` trait keeps working as a **deprecated** wrapper (it will be removed in v3), so you can migrate one call at a time.

v1 is frozen on the `1.x` branch and only receives security fixes.

### Requirements

- PHP **8.2+** (8.3+ for Laravel 13)
- Laravel **12.x or 13.x**
- `intervention/image` **^3.11** (it was pinned to `3.11.2` in v1)

### 1. Update the dependency

```bash
composer require daycode/stup-images:^2.0
```

### 2. Update the config (only if you published it)

If you published `config/stup-image.php` in v1, re-publish it. The v1 keys are no longer read:

```bash
php artisan vendor:publish --tag=stup-image-config --force
```

| v1 | v2 |
|---|---|
| `'hash_filename' => true` | `'naming' => 'ulid'` (default), `'uuid'` or `'hash'` |
| `'hash_filename' => false` | `'naming' => 'original'` (slugged and deduplicated: `my-photo.jpg`, `my-photo-1.jpg`) |
| `'allowed_extensions' => ['*']` | `'allowed_mime_types' => null` (anything the driver can decode) |
| `'allowed_extensions' => ['jpg', 'png']` | `'allowed_mime_types' => ['image/jpeg', 'image/png']` |

New options: `disk`, `directory`, `driver`, `visibility`, `max_size`, `max_width`, `max_height`, `default_format`, `quality` and `presets`. See the [README](README.md#configuration).

> **Behaviour change:** v1 accepted every extension by default. v2 accepts only `image/jpeg`, `image/png`, `image/gif`, `image/webp` and `image/avif` by default, up to 10 MB. The MIME type is detected from the file contents.

### 3. Handle exceptions (breaking)

v1 **returned** an `UploadException` object on failure, which could end up saved as a filename. v2 **throws** a `Daycode\StupImage\Exceptions\StupImageException` subclass. This also applies to the deprecated `Stupable` methods.

```php
// v1
$result = $this->uploadFile($file, 'avatars');
if ($result instanceof UploadException) { /* ... */ }

// v2
try {
    $image = StupImage::from($file)->directory('avatars')->store();
} catch (StupImageException $e) {
    // ...
}
```

### 4. Store paths instead of filenames (recommended)

v1 returned only the filename, so you had to remember the directory to build URLs or delete files. v2 returns a `StoredImage` whose `path` includes the directory. Storing the path is recommended:

```php
$user->avatar = $image->path; // avatars/01j9zk3v7c8e2x4m6n8p0q2r4s.jpg
```

The `Stupable` wrapper still returns filenames, so existing data keeps working.

### 5. Replace the `Stupable` calls

| v1 | v2 |
|---|---|
| `$this->uploadFile($file, 'avatars')` | `StupImage::from($file)->directory('avatars')->store()` |
| `$this->uploadFile($file, 'avatars', [800, 600])` | `StupImage::from($file)->directory('avatars')->resize(800, 600)->store()` |
| `$this->syncUploadFile($file, $old, 'avatars')` | `StupImage::replace($old, $file)->directory('avatars')->store()` |
| `$this->uploadMultipleFiles($files, 'gallery')` | `StupImage::many($files)->directory('gallery')->store()` |
| `$this->deleteFile($name, 'avatars')` | `StupImage::delete('avatars/'.$name)` |

With v1 filenames stored in the database, prefix the directory: `StupImage::replace('avatars/'.$user->avatar, $file)`.

`resize(800, 600)` behaves like v1 and ignores the aspect ratio. You'll usually want `cover(800, 600)` (crop to fill) or `scale(width: 800)` (keep the aspect ratio) instead.

### 6. Other changes

- **Storage is disk-agnostic.** v1 wrote through `Storage::path()`, so only local disks worked. v2 uses `Storage::disk()->put()`, which also works with S3 and other cloud disks.
- **Replacing is safe.** v1's `syncUploadFile()` deleted the old file *before* uploading, so a failed upload lost it. v2 deletes the old file only after the new one has been stored.
- **`syncUploadFile()` and `uploadMultipleFiles()` accept a `null` path.** v1 threw a `TypeError`.
- **Unique names.** v1 used `md5(time().$name)`, which collided for identical names in the same second. v2 uses a ULID by default.
- **No double extensions.** With hashing disabled, v1 produced `photo.jpg.jpg`. v2 produces `photo.jpg`.
- **Case-insensitive MIME checks.** v1 rejected `PHOTO.JPG` when `jpg` was allowed. v2 checks the real MIME type.
- **Driver.** v1 hard-coded GD. v2 reads `STUP_IMAGE_DRIVER` (`gd` or `imagick`).
- **Removed:** the internal `Daycode\StupImage\Services\Intervention` class. Use `StupImage::from()` or implement `Daycode\StupImage\Contracts\ImageProcessor`.
- **Publish tag.** The config is now published with `--tag=stup-image-config`. The v1 tag `--tag=stup-image` still works.
