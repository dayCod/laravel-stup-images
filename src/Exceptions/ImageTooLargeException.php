<?php

declare(strict_types=1);

namespace Daycode\StupImage\Exceptions;

final class ImageTooLargeException extends StupImageException
{
    public static function fileSize(string $name, int $sizeInKilobytes, int $maxInKilobytes): self
    {
        return new self("The file [{$name}] is {$sizeInKilobytes} KB, the maximum allowed size is {$maxInKilobytes} KB.");
    }

    public static function dimensions(string $name, int $width, int $height, ?int $maxWidth, ?int $maxHeight): self
    {
        return new self(sprintf(
            'The image [%s] is %dx%d pixels, the maximum allowed dimensions are %sx%s pixels.',
            $name,
            $width,
            $height,
            $maxWidth ?? '∞',
            $maxHeight ?? '∞',
        ));
    }
}
