<?php

declare(strict_types=1);

namespace Daycode\StupImage\Exceptions;

use Throwable;

final class StorageException extends StupImageException
{
    public static function unableToWrite(string $disk, string $path, ?Throwable $previous = null): self
    {
        return new self("Unable to write [{$path}] to disk [{$disk}].", previous: $previous);
    }

    public static function unableToRead(string $disk, string $path, ?Throwable $previous = null): self
    {
        return new self("Unable to read [{$path}] from disk [{$disk}].", previous: $previous);
    }

    public static function unableToDelete(string $disk, string $path, ?Throwable $previous = null): self
    {
        return new self("Unable to delete [{$path}] from disk [{$disk}].", previous: $previous);
    }
}
