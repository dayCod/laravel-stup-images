<?php

declare(strict_types=1);

namespace Daycode\StupImage\Contracts;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;

interface Namer
{
    /**
     * Generate the filename (including the extension) for an uploaded image.
     */
    public function name(UploadedFile $file, string $extension, string $directory, Filesystem $disk): string;
}
