<?php

declare(strict_types=1);

namespace Daycode\StupImage\Events;

use Daycode\StupImage\StoredImage;

final class ImageStored
{
    public function __construct(public readonly StoredImage $image) {}
}
