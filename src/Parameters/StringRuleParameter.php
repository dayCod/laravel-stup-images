<?php

namespace DayCod\StupImages\Parameters;

use InvalidArgumentException;

final class StringRuleParameter
{
    public function __construct(public readonly string $string)
    {
        if ($string == "") {
            throw new InvalidArgumentException("InvalidArgumentException: input param cannot be an empty string");
        }
    }

    public static function string(string $string): self
    {
        return new self($string);
    }
}
