<?php

namespace DayCod\StupImages;

use DayCod\StupImages\Functions\StupImageFunctions;
use DayCod\StupImages\Parameters\FilePathParameter;
use DayCod\StupImages\Parameters\ImageRatioParameter;
use DayCod\StupImages\Parameters\StringRuleParameter;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image as ImageIntervention;
use InvalidArgumentException;

class StupImagesConstructor
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
    public function __construct(
        StringRuleParameter $filename,
        StringRuleParameter $path,
        ImageRatioParameter $width,
        ImageRatioParameter $height
    )
    {
        $this->filename = $filename;
        $this->path = $path;
        $this->width = $width;
        $this->height = $height;
    }

    /**
     * Store images to storage folder and resize it.
     *
     * @return $url
     * @var string
     */
    public function storeToStorage(mixed $new_image_file, mixed $old_image_file)
    {
        if (!in_array($new_image_file->getClientOriginalExtension(), ['jpg', 'jpeg', 'png'])) {
            throw new InvalidArgumentException("InvalidArgumentException: please input a valid image format, `.jpg, .jpeg, .png`");
        }
        $formatted_picture = $this->filename->string.'-'.time().'.'.$new_image_file->getClientOriginalExtension();

        if (!file_exists(storage_path("app/public/".$this->path->string."/"))) {
            mkdir(storage_path("app/public/".$this->path->string."/"), 0775, true);
        }

        if ((!is_null($old_image_file)) && Storage::exists("/public/".$this->path->string."/".StupImageFunctions::getFileName($old_image_file))) {
            Storage::delete("/public/".$this->path->string."/".StupImageFunctions::getFileName($old_image_file));
        }

        ImageIntervention::make($new_image_file)->resize($this->width->ratio, $this->height->ratio)->save(storage_path("app/public/".$this->path->string."/".$formatted_picture));

        return url("storage/".$this->path->string."/$formatted_picture");
    }
}
