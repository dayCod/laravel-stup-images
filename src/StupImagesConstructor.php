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
     * Store images to storage folder and resize it.
     *
     * @return $url
     * @var string
     */
    public function storeToStorage(
        StringRuleParameter $filename,
        StringRuleParameter $path,
        ImageRatioParameter $width,
        ImageRatioParameter $height,
        mixed $new_image_file,
        mixed $old_image_file)
    {
        if (!in_array($new_image_file->getClientOriginalExtension(), ['jpg', 'jpeg', 'png'])) {
            throw new InvalidArgumentException("InvalidArgumentException: please input a valid image format, `.jpg, .jpeg, .png`");
        }

        $formatted_picture = $filename->string.'-'.time().'.'.$new_image_file->getClientOriginalExtension();

        if (!file_exists(storage_path("app/public/".$path->string."/"))) {
            mkdir(storage_path("app/public/".$path->string."/"), 0775, true);
        }

        if ((!is_null($old_image_file)) && Storage::exists("/public/".$path->string."/".StupImageFunctions::getFileName($old_image_file))) {
            Storage::delete("/public/".$path->string."/".StupImageFunctions::getFileName($old_image_file));
        }

        ImageIntervention::make($new_image_file)->resize($width->ratio, $height->ratio)->save(storage_path("app/public/".$path->string."/".$formatted_picture));

        return url("storage/".$path->string."/$formatted_picture");
    }
}
