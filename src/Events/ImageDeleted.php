<?php

declare(strict_types=1);

namespace Daycode\StupImage\Events;

final class ImageDeleted
{
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
    ) {}
}
