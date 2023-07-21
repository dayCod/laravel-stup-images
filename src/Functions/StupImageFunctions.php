<?php

namespace DayCod\StupImages\Functions;

class StupImageFunctions
{
    /**
     * Get filename from storage path
     *
     * @return $filename
     * @var string
     */
    public static function getFileName(string $file_path)
    {
        $explode = explode('/', $file_path);
        $parse_last_index = $explode[(count($explode) - 1)];

        return $parse_last_index;
    }
}
