<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk images are stored on. Any disk from
    | config/filesystems.php works (local, public, s3, ...).
    | When null, the default filesystem disk is used.
    |
    */
    'disk' => env('STUP_IMAGE_DISK'),

    /*
    |--------------------------------------------------------------------------
    | Directory
    |--------------------------------------------------------------------------
    |
    | The default directory inside the disk. Override it per upload with
    | ->directory('avatars').
    |
    */
    'directory' => 'images',

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | The image library used by Intervention Image: "gd" or "imagick".
    | Run `php artisan stup-image:install` to check which ones are available.
    |
    */
    'driver' => env('STUP_IMAGE_DRIVER', 'gd'),

    /*
    |--------------------------------------------------------------------------
    | Visibility
    |--------------------------------------------------------------------------
    |
    | The visibility ("public" or "private") of stored images. When null, the
    | disk's own visibility setting is used. Note that new S3 buckets have ACLs
    | disabled, in which case setting a visibility makes uploads fail.
    |
    */
    'visibility' => env('STUP_IMAGE_VISIBILITY'),

    /*
    |--------------------------------------------------------------------------
    | Naming
    |--------------------------------------------------------------------------
    |
    | How stored files are named:
    |   "ulid"     - 01j9zk3v7c8e2x4m6n8p0q2r4s.jpg (sortable, default)
    |   "uuid"     - 0192d5a4-7b3c-7d2e-9f1a-3b5c7d9e1f2a.jpg
    |   "hash"     - 9f86d081884c7d659a2feaa0c55ad015.jpg
    |   "original" - my-photo.jpg, my-photo-1.jpg, ... (slug of the client name)
    |
    | You may also use the class name of a Daycode\StupImage\Contracts\Namer.
    |
    */
    'naming' => 'ulid',

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    |
    | The MIME type is detected from the file contents, not from the client
    | extension. Set "allowed_mime_types" to null to accept anything the image
    | driver can decode. Sizes are in kilobytes, dimensions in pixels; null
    | disables the limit.
    |
    */
    'allowed_mime_types' => [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'image/avif',
    ],

    'max_size' => 10 * 1024,

    'max_width' => null,

    'max_height' => null,

    /*
    |--------------------------------------------------------------------------
    | Output
    |--------------------------------------------------------------------------
    |
    | "default_format" converts every image (e.g. "webp"); null keeps the
    | original format. "quality" (1-100) is used for lossy formats.
    |
    */
    'default_format' => null,

    'quality' => 85,

    /*
    |--------------------------------------------------------------------------
    | Presets
    |--------------------------------------------------------------------------
    |
    | Named sets of options, applied with ->preset('thumbnail') or stored as
    | extra sizes with ->variants(['thumbnail']). Supported keys:
    | resize, cover, scale, contain (as [width, height]), format and quality.
    |
    */
    'presets' => [
        'thumbnail' => ['cover' => [150, 150], 'format' => 'webp', 'quality' => 80],
        'medium' => ['scale' => [800, null]],
    ],

];
