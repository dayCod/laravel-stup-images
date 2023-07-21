<?php

namespace DayCod\StupImages\Parameters;

use Psy\Exception\TypeErrorException;

final class ImageRatioParameter
{
    const CONTINUE = true;

    public function __construct(public readonly int $ratio)
    {
        if ($ratio < 0) {
            throw new TypeErrorException('Parameter Value must greater than zero');
        }
    }

    public static function ratio(int $ratio): self
    {
        return new self($ratio);
    }
}
