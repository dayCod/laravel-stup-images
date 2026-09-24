<?php

declare(strict_types=1);

namespace Daycode\StupImage\Naming;

use Daycode\StupImage\Contracts\Namer;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;

final class HashNamer implements Namer
{
    public function name(UploadedFile $file, string $extension, string $directory, Filesystem $disk): string
    {
        return bin2hex(random_bytes(16)).'.'.$extension;
    }
}
