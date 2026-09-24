<?php

declare(strict_types=1);

namespace Daycode\StupImage\Facades;

use Daycode\StupImage\StupImageManager;
use Daycode\StupImage\Testing\StupImageFake;
use Illuminate\Support\Facades\Facade;
use RuntimeException;

/**
 * @method static \Daycode\StupImage\ImageUpload from(mixed $file)
 * @method static \Daycode\StupImage\ImageUpload replace(?string $oldPath, mixed $file)
 * @method static \Daycode\StupImage\ImageBatch many(iterable<mixed>|null $files)
 * @method static \Daycode\StupImage\StupImageManager disk(?string $disk)
 * @method static bool delete(?string $path)
 * @method static bool exists(?string $path)
 * @method static string|null url(?string $path, ?string $variant = null)
 * @method static string variantPath(string $path, string $variant)
 * @method static array{manipulations: list<array{0: string, 1: int|null, 2: int|null}>, format: string|null, quality: int|null} preset(string $name)
 * @method static list<string> presetNames()
 * @method static string defaultDisk()
 *
 * @see StupImageManager
 */
final class StupImage extends Facade
{
    /**
     * Replace the manager with a fake that writes to faked disks and records every operation.
     */
    public static function fake(): StupImageFake
    {
        $app = self::getFacadeApplication() ?? throw new RuntimeException('The StupImage facade has no application instance.');
        $fake = new StupImageFake($app);

        self::swap($fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return StupImageManager::class;
    }
}
