<?php

declare(strict_types=1);

namespace Daycode\StupImage\Naming;

use Daycode\StupImage\Contracts\Namer;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class UlidNamer implements Namer
{
    public function name(UploadedFile $file, string $extension, string $directory, Filesystem $disk): string
    {
        return strtolower((string) Str::ulid()).'.'.$extension;
    }
}
