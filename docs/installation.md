# Installation

## Requirements

- PHP 8.2 – 8.5 (Laravel 13 needs PHP 8.3+)
- Laravel 12.x or 13.x
- The PHP `gd` or `imagick` extension

## Install

```bash
composer require daycode/stup-images
```

The service provider and the `StupImage` facade alias are auto-discovered.

## Publish the config and check the driver (optional)

```bash
php artisan stup-image:install
```

The command publishes `config/stup-image.php` (use `--force` to overwrite it) and reports whether `gd` and `imagick` are available. It fails if the configured driver is missing.

You can also publish the config only:

```bash
php artisan vendor:publish --tag=stup-image-config
```

## Choose a disk

By default, images go to the default filesystem disk (`FILESYSTEM_DISK`). To use a different disk just for images:

```env
STUP_IMAGE_DISK=public
```

When you use the `public` disk, link the storage folder once:

```bash
php artisan storage:link
```

Any disk from `config/filesystems.php` works, including S3-compatible storage such as AWS S3, Cloudflare R2, DigitalOcean Spaces and MinIO.

## Choose a driver

GD is the default. To use Imagick, which is often faster and supports more formats:

```env
STUP_IMAGE_DRIVER=imagick
```
