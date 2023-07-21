<?php

namespace DayCod\StupImages\Parameters;

use InvalidArgumentException;

final class FilePathParameter
{
    const CONTINUE = true;

    public function __construct(public readonly string $file_path)
    {
        if ($file_path == "null") {
            $explode = explode('/', $file_path);
            $parse_last_index = explode('.', $explode[(count($explode) - 1)]);
            $get_item_format = $parse_last_index[(count($parse_last_index) - 1)];

            if ($file_path == "") {
                throw new InvalidArgumentException("InvalidArgumentException: input param cannot be an empty string");
            } elseif (!in_array($get_item_format, ['jpg', 'jpeg', 'png'])) {
                throw new InvalidArgumentException("InvalidArgumentException: please input a valid image format, `.jpg, .jpeg, .png`");
            }
        }
        null;
    }

    public static function filePath(string $file_path): self
    {
        return new self($file_path);
    }
}
