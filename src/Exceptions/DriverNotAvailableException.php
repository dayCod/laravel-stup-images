<?php

declare(strict_types=1);

namespace Daycode\StupImage\Exceptions;

use Throwable;

final class DriverNotAvailableException extends StupImageException
{
    public static function unsupported(string $driver): self
    {
        return new self("Unsupported image driver [{$driver}]. Supported drivers are: gd, imagick.");
    }

    public static function missingExtension(string $driver, ?Throwable $previous = null): self
    {
        return new self(
            "The [{$driver}] PHP extension is not available. Install it or set STUP_IMAGE_DRIVER to another driver. "
            .'Run `php artisan stup-image:install` to check your environment.',
            previous: $previous,
        );
    }
}
