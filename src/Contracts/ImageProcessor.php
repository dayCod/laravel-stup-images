<?php

declare(strict_types=1);

namespace Daycode\StupImage\Contracts;

use Daycode\StupImage\Exceptions\InvalidImageException;
use Daycode\StupImage\ProcessedImage;

interface ImageProcessor
{
    /**
     * Decode the given binary image, apply the manipulations and encode it.
     *
     * Each manipulation is a tuple of [operation, width, height] where the
     * operation is one of "resize", "cover", "scale" or "contain".
     *
     * @param  list<array{0: string, 1: int|null, 2: int|null}>  $manipulations
     * @param  string|null  $format  Target file extension (e.g. "webp"), null keeps the source format.
     *
     * @throws InvalidImageException
     */
    public function process(string $contents, array $manipulations = [], ?string $format = null, int $quality = 85): ProcessedImage;
}
