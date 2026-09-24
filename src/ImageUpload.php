<?php

declare(strict_types=1);

namespace Daycode\StupImage;

use Daycode\StupImage\Exceptions\StupImageException;
use Illuminate\Http\UploadedFile;
use Throwable;

final class ImageUpload extends PendingUpload
{
    private ?string $name = null;

    private ?string $replacing = null;

    public function __construct(StupImageManager $manager, private readonly mixed $file)
    {
        parent::__construct($manager);
    }

    /**
     * Use an explicit filename (without extension) instead of the configured naming strategy.
     * An existing file with the same name is overwritten.
     */
    public function name(string $name): self
    {
        $this->name = self::sanitizeName($name);

        return $this;
    }

    /**
     * Delete the given path once the new image has been stored successfully.
     */
    public function replacing(?string $path): self
    {
        $this->replacing = $path === '' ? null : $path;

        return $this;
    }

    /**
     * @throws StupImageException
     */
    public function store(): StoredImage
    {
        $namer = $this->name === null
            ? null
            : fn (UploadedFile $file, string $extension): string => "{$this->name}.{$extension}";

        $image = $this->storeFile($this->file, $namer);

        if ($this->replacing !== null && $this->replacing !== $image->path) {
            try {
                $this->manager->disk($image->disk)->delete($this->replacing);
            } catch (Throwable $e) {
                // The new image is stored; a leftover old file must not fail the request.
                report($e);
            }
        }

        return $image;
    }
}
