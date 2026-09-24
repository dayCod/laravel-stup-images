<?php

declare(strict_types=1);

namespace Daycode\StupImage\Jobs;

use Daycode\StupImage\Exceptions\StorageException;
use Daycode\StupImage\StupImageManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Generates the variants of an already stored image. The stored image is used as source.
 */
final class GenerateImageVariants implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $variants
     */
    public function __construct(
        public readonly string $disk,
        public readonly string $path,
        public readonly array $variants,
        public readonly int $quality,
        public readonly ?string $visibility = null,
    ) {}

    public function handle(StupImageManager $manager): void
    {
        $disk = $manager->filesystem($this->disk);

        // The image may have been deleted or replaced before the job ran.
        if (! $disk->exists($this->path)) {
            return;
        }

        $source = $disk->get($this->path);

        if ($source === null) {
            throw StorageException::unableToRead($this->disk, $this->path);
        }

        foreach ($manager->createVariants($this->disk, $this->path, $source, $this->variants, $this->quality, $this->visibility) as $variant) {
            $manager->stored($variant);
        }
    }
}
