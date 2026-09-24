<?php

declare(strict_types=1);

namespace Daycode\StupImage\Testing;

use Closure;
use Daycode\StupImage\StoredImage;
use Daycode\StupImage\StupImageManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Assert as PHPUnit;

/**
 * Processes images for real but writes them to faked disks and records every operation.
 */
class StupImageFake extends StupImageManager
{
    private FakeRecords $records;

    public function __construct(Container $container)
    {
        parent::__construct($container);

        $this->records = new FakeRecords;
    }

    public function filesystem(string $disk): Filesystem
    {
        if (! isset($this->records->fakedDisks[$disk])) {
            Storage::fake($disk);
            $this->records->fakedDisks[$disk] = true;
        }

        return parent::filesystem($disk);
    }

    public function stored(StoredImage $image): void
    {
        $this->records->stored[] = $image;

        parent::stored($image);
    }

    public function deleted(string $disk, string $path): void
    {
        $this->records->deleted[] = ['disk' => $disk, 'path' => $path];

        parent::deleted($disk, $path);
    }

    /**
     * @return Collection<int, StoredImage>
     */
    public function storedImages(): Collection
    {
        return new Collection($this->records->stored);
    }

    /**
     * @return Collection<int, array{disk: string, path: string}>
     */
    public function deletedImages(): Collection
    {
        return new Collection($this->records->deleted);
    }

    /**
     * Assert an image was stored, optionally by path or by a truth-test callback.
     *
     * @param  string|(Closure(StoredImage): bool)|null  $path
     */
    public function assertStored(string|Closure|null $path = null, ?int $times = null): static
    {
        $matches = $this->storedImages()->filter(fn (StoredImage $image): bool => match (true) {
            $path === null => true,
            is_string($path) => $image->path === $path,
            default => (bool) $path($image),
        });

        if ($times === null) {
            PHPUnit::assertTrue($matches->isNotEmpty(), is_string($path)
                ? "The expected image [{$path}] was not stored."
                : 'The expected image was not stored.');
        } else {
            PHPUnit::assertCount($times, $matches, "The expected image was stored {$matches->count()} times instead of {$times} times.");
        }

        $image = $matches->last();

        if (is_string($path) && $image !== null) {
            PHPUnit::assertTrue(
                $this->filesystem($image->disk)->exists($path),
                "The image [{$path}] was stored but no longer exists on disk [{$image->disk}].",
            );
        }

        return $this;
    }

    public function assertStoredCount(int $count): static
    {
        PHPUnit::assertCount($count, $this->records->stored, "Expected {$count} stored images, got ".count($this->records->stored).'.');

        return $this;
    }

    public function assertNothingStored(): static
    {
        PHPUnit::assertEmpty($this->records->stored, 'Images were stored unexpectedly: '.implode(', ', array_map(
            fn (StoredImage $image): string => $image->path,
            $this->records->stored,
        )));

        return $this;
    }

    public function assertDeleted(string $path): static
    {
        PHPUnit::assertTrue(
            $this->deletedImages()->contains(fn (array $deleted): bool => $deleted['path'] === $path),
            "The expected image [{$path}] was not deleted.",
        );

        return $this;
    }

    public function assertNotDeleted(string $path): static
    {
        PHPUnit::assertFalse(
            $this->deletedImages()->contains(fn (array $deleted): bool => $deleted['path'] === $path),
            "The image [{$path}] was deleted unexpectedly.",
        );

        return $this;
    }

    public function assertNothingDeleted(): static
    {
        PHPUnit::assertEmpty($this->records->deleted, 'Images were deleted unexpectedly.');

        return $this;
    }
}
