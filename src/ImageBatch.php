<?php

declare(strict_types=1);

namespace Daycode\StupImage;

use Daycode\StupImage\Exceptions\StupImageException;
use Illuminate\Support\Collection;
use Throwable;

final class ImageBatch extends PendingUpload
{
    /**
     * @param  iterable<mixed>  $files
     */
    public function __construct(StupImageManager $manager, private readonly iterable $files)
    {
        parent::__construct($manager);
    }

    /**
     * Store every file. When one fails, the files stored so far are deleted.
     *
     * @return Collection<int, StoredImage>
     *
     * @throws StupImageException
     */
    public function store(): Collection
    {
        /** @var Collection<int, StoredImage> $stored */
        $stored = new Collection;

        try {
            foreach ($this->files as $file) {
                $stored->push($this->storeFile($file, announce: false));
            }
        } catch (Throwable $e) {
            $stored->each(fn (StoredImage $image) => $this->manager->discard($image));

            throw $e;
        }

        $stored->each(fn (StoredImage $image) => $this->manager->stored($image));

        return $stored;
    }
}
