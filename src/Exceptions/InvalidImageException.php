<?php

declare(strict_types=1);

namespace Daycode\StupImage\Exceptions;

use Throwable;

final class InvalidImageException extends StupImageException
{
    public static function notAnUploadedFile(mixed $value): self
    {
        return new self(sprintf('Expected an instance of UploadedFile, [%s] given.', get_debug_type($value)));
    }

    public static function invalidUpload(string $name, string $reason): self
    {
        return new self("The file [{$name}] was not uploaded successfully: {$reason}");
    }

    public static function unreadable(string $name, ?Throwable $previous = null): self
    {
        return new self("The file [{$name}] could not be decoded as an image.", previous: $previous);
    }
}
