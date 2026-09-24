<?php

declare(strict_types=1);

namespace Daycode\StupImage\Exceptions;

final class DisallowedMimeTypeException extends StupImageException
{
    /**
     * @param  list<string>  $allowed
     */
    public static function make(string $name, string $mimeType, array $allowed): self
    {
        return new self(sprintf(
            'The file [%s] has MIME type [%s], allowed types are: %s.',
            $name,
            $mimeType,
            implode(', ', $allowed),
        ));
    }
}
