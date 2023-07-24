<?php

namespace DayCod\StupImages;

use DayCod\StupImages\Parameters\FilePathParameter;
use DayCod\StupImages\Parameters\ImageRatioParameter;
use DayCod\StupImages\Parameters\StringRuleParameter;

class StupImages
{
    /**
     * Get the Filename of Class Constructor.
     *
     * @return string
     */
    protected $filename;

    /**
     * Get the Path of Class Constructor.
     *
     * @return string
     */
    protected $path;

    /**
     * Get the Width of Class Constructor.
     *
     * @return int
     */
    protected $width;

    /**
     * Get the Height of Class Constructor.
     *
     * @return int
     */
    protected $height;

    /**
     * instantiate an Image of a Class Constructor.
     *
     */
    public function __construct(string $filename, string $path, int $width, int $height)
    {
        $this->filename = $filename;
        $this->path = $path;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * store image file to storage and return url string as a result.
     *
     * @return string $url
     */
    public function StupImagesToStorage(mixed $new_image_file, mixed $old_image_file = null)
    {
        return (new StupImagesConstructor())->storeToStorage(
            StringRuleParameter::string($this->filename),
            StringRuleParameter::string($this->path),
            ImageRatioParameter::ratio($this->width),
            ImageRatioParameter::ratio($this->height),
            $new_image_file,
            $old_image_file);
    }
}
