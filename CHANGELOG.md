# Changelog

All notable changes to `daycode/stup-images` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-09-24

A rewrite: disk-agnostic, fully tested and simpler to use. See [UPGRADE.md](UPGRADE.md).

### Added

- `StupImage` facade with a fluent builder: `StupImage::from($file)->directory()->cover()->toWebp()->store()`.
- Resize operations `cover`, `scale`, `contain` and `resize`, and output formats `toWebp`, `toAvif`, `toJpeg`, `toPng`, `format` and `quality`.
- `StoredImage` value object with `path`, `filename`, `disk`, `mimeType`, `size`, `width`, `height`, `url()`, `temporaryUrl()`, `exists()`, `delete()`, `toArray()` and `__toString()`.
- `StupImage::replace()`: deletes the old image only after the new one has been stored.
- `StupImage::many()`: all-or-nothing batch uploads that return a `Collection<StoredImage>`.
- `StupImage::delete()`, `StupImage::url()`, `StupImage::exists()` and `StupImage::disk()`.
- Naming strategies `ulid` (default), `uuid`, `hash`, `original` or a custom `Namer` class, plus `->name()` for explicit names.
- Validation of the real MIME type (`allowed_mime_types`), `max_size`, `max_width` and `max_height`.
- Configurable `gd` or `imagick` driver (`STUP_IMAGE_DRIVER`).
- Exception hierarchy: `StupImageException`, `InvalidImageException`, `DisallowedMimeTypeException`, `ImageTooLargeException`, `StorageException` and `DriverNotAvailableException`.
- Presets (`->preset('thumbnail')`).
- Variants (`->variants(['thumbnail', 'medium'])`) stored as `{directory}/{name}/{preset}.{ext}`, optionally generated on the queue (`->queue()`).
- `StupImage::fake()` with `assertStored`, `assertStoredCount`, `assertNothingStored`, `assertDeleted`, `assertNotDeleted` and `assertNothingDeleted`.
- `ImageStored` and `ImageDeleted` events.
- `HasStupImages` Eloquent trait: stores uploaded files assigned to attributes, deletes replaced images after saving, and adds `{attribute}Url()` helpers.
- `UploadedFile::storeImage()` macro.
- `php artisan stup-image:install` command.
- Pest test suite, Larastan at level max, Pint and GitHub Actions for PHP 8.2–8.5 × Laravel 12–13.

### Changed

- **Breaking:** requires PHP 8.2+, Laravel 12 or 13, and `intervention/image` ^3.11.
- **Breaking:** failures throw a `StupImageException` instead of returning an `UploadException`.
- **Breaking:** the config keys `hash_filename` and `allowed_extensions` were replaced by `naming` and `allowed_mime_types`. By default, only common image MIME types up to 10 MB are accepted.
- Images are written with `Storage::disk()->put()`, so S3 and other cloud disks work.
- The service provider uses `spatie/laravel-package-tools`. The config is published with `--tag=stup-image-config` (the old `--tag=stup-image` still works).

### Deprecated

- The `Stupable` trait. It now wraps the new API and will be removed in v3.

### Removed

- **Breaking:** the internal `Daycode\StupImage\Services\Intervention` class.

### Fixed

- Storing on non-local disks (B1).
- Exceptions being returned (and possibly saved as filenames) instead of thrown (B2, B4).
- `syncUploadFile()` deleting the old file before the new upload succeeded (B3).
- `TypeError` when passing a `null` path to `syncUploadFile()` (B5).
- `photo.jpg.jpg` filenames when hashing was disabled (B6).
- Extension checks that were case-sensitive and trusted the client (B7).
- Filename collisions for identical names uploaded in the same second (B8).
- Non-image files being accepted by default (B9).
- `resize()` distorting images and GD being hard-coded (B10).
- Missing PHP and Laravel constraints and `minimum-stability: dev` (B11).

## [1.3.0] - 2025-12-11

- Stupable trait with `uploadFile`, `syncUploadFile`, `uploadMultipleFiles` and `deleteFile`.
- `StupImageServiceProvider` and a publishable config.

[2.0.0]: https://github.com/dayCod/laravel-stup-images/compare/v1.3.0...v2.0.0
[1.3.0]: https://github.com/dayCod/laravel-stup-images/releases/tag/v1.3.0
