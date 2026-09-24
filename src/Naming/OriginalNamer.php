<?php

declare(strict_types=1);

namespace Daycode\StupImage\Naming;

use Daycode\StupImage\Contracts\Namer;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Keeps the client filename (slugged) and appends a counter when it is taken: photo.jpg, photo-1.jpg, ...
 *
 * The base name must be unique regardless of the extension, because variants of
 * "photo.jpg" live in the "photo/" folder and would otherwise be shared with "photo.png".
 */
final class OriginalNamer implements Namer
{
    private const EXTENSIONS = ['jpg', 'png', 'gif', 'webp', 'avif', 'bmp', 'tif', 'heic'];

    public function name(UploadedFile $file, string $extension, string $directory, Filesystem $disk): string
    {
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

        if ($base === '') {
            $base = 'image';
        }

        $prefix = $directory === '' ? '' : $directory.'/';
        $candidate = $base;

        for ($i = 1; $this->taken($disk, $prefix.$candidate, $extension); $i++) {
            $candidate = "{$base}-{$i}";
        }

        return "{$candidate}.{$extension}";
    }

    private function taken(Filesystem $disk, string $stem, string $extension): bool
    {
        foreach (array_unique([$extension, ...self::EXTENSIONS]) as $candidate) {
            if ($disk->exists("{$stem}.{$candidate}")) {
                return true;
            }
        }

        return false;
    }
}
