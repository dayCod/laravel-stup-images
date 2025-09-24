<?php

declare(strict_types=1);

namespace Daycode\StupImage\Services;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class Intervention
{
    /**
     * @var ImageManager
     */
    protected $manager;

    /**
     * @var \Intervention\Image\Image
     */
    public $image;

    /**
     * @var string
     */
    public $imageName;

    /**
     * @var string
     */
    public $path;

    /**
     * Intervention constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->manager = new ImageManager(new Driver);
    }

    /**
     * Read image
     */
    public function read(?UploadedFile $file): self
    {
        $this->image = $this->manager->read($file);

        return $this;
    }

    /**
     * Set image name
     */
    public function setImageName(?string $name): self
    {
        $this->imageName = $name;

        return $this;
    }

    /**
     * Set image path
     */
    public function setPath(?string $path): self
    {
        $this->path = $path;

        return $this;
    }

    /**
     * Resize image
     */
    public function resize(int $width, int $height): self
    {
        $this->image = $this->image->resize($width, $height);

        return $this;
    }

    /**
     * Save image
     */
    public function save(): string
    {
        if (! file_exists($this->path)) {
            mkdir($this->path, 0775, true);
        }

        $this->image->save($this->path.'/'.$this->imageName);

        return $this->imageName;
    }
}
