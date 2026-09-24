<?php

declare(strict_types=1);

use Daycode\StupImage\Events\ImageStored;
use Daycode\StupImage\Exceptions\StorageException;
use Daycode\StupImage\Facades\StupImage;
use Daycode\StupImage\Jobs\GenerateImageVariants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

describe('presets', function (): void {
    it('applies a preset from the config', function (): void {
        $image = StupImage::from(UploadedFile::fake()->image('a.jpg', 600, 400))->preset('thumbnail')->store();

        expect([$image->width, $image->height, $image->mimeType])->toBe([150, 150, 'image/webp']);
    });

    it('applies every supported preset key', function (): void {
        config(['stup-image.presets.custom' => [
            'resize' => [400, 400],
            'cover' => [300, 200],
            'scale' => 150,
            'contain' => [150, 150],
            'format' => 'png',
            'quality' => 50,
        ]]);

        $image = StupImage::from(UploadedFile::fake()->image('a.jpg', 600, 400))->preset('custom')->store();

        expect([$image->width, $image->height, $image->mimeType])->toBe([150, 150, 'image/png']);
    });

    it('lets options after the preset override it', function (): void {
        $image = StupImage::from(UploadedFile::fake()->image('a.jpg', 600, 400))->preset('thumbnail')->toJpeg()->store();

        expect($image->mimeType)->toBe('image/jpeg');
    });

    it('rejects an unknown preset', function (): void {
        StupImage::from(UploadedFile::fake()->image('a.jpg'))->preset('huge');
    })->throws(InvalidArgumentException::class, 'Image preset [huge] is not defined');
});

describe('variants', function (): void {
    it('stores variants next to the image', function (): void {
        $image = StupImage::from(UploadedFile::fake()->image('a.jpg', 1600, 1200))
            ->directory('avatars')
            ->variants(['thumbnail', 'medium'])
            ->store();

        $stem = pathinfo($image->filename, PATHINFO_FILENAME);

        expect(array_keys($image->variants))->toBe(['thumbnail', 'medium'])
            ->and($image->variant('thumbnail')->path)->toBe("avatars/{$stem}/thumbnail.webp")
            ->and([$image->variant('thumbnail')->width, $image->variant('thumbnail')->height])->toBe([150, 150])
            ->and($image->variant('medium')->path)->toBe("avatars/{$stem}/medium.jpg")
            ->and([$image->variant('medium')->width, $image->variant('medium')->height])->toBe([800, 600])
            ->and([$image->width, $image->height])->toBe([1600, 1200]);

        Storage::disk('local')->assertExists([$image->path, $image->variant('thumbnail')->path, $image->variant('medium')->path]);
    });

    it('accepts variadic preset names and dedupes them', function (): void {
        $image = StupImage::from(UploadedFile::fake()->image('a.jpg'))->variants('thumbnail', 'thumbnail')->store();

        expect(array_keys($image->variants))->toBe(['thumbnail']);
    });

    it('uses the output format of the image for variants without a format', function (): void {
        $image = StupImage::from(UploadedFile::fake()->image('a.jpg'))->toPng()->variants(['medium'])->store();

        expect($image->variant('medium')->path)->toEndWith('/medium.png')
            ->and($image->variant('medium')->mimeType)->toBe('image/png');
    });

    it('computes variant paths and urls from the stored path', function (): void {
        Storage::fake('public');
        $image = StupImage::disk('public')->from(UploadedFile::fake()->image('a.jpg'))->directory('avatars')->variants(['thumbnail'])->store();

        expect(StupImage::variantPath($image->path, 'thumbnail'))->toBe($image->variant('thumbnail')->path)
            ->and(StupImage::variantPath('a.jpg', 'medium'))->toBe('a/medium.jpg')
            ->and(StupImage::disk('public')->url($image->path, 'thumbnail'))->toBe($image->variant('thumbnail')->url());
    });

    it('deletes variants together with the image', function (): void {
        $image = StupImage::from(UploadedFile::fake()->image('a.jpg'))->directory('avatars')->variants(['thumbnail', 'medium'])->store();
        Storage::disk('local')->put('avatars/unrelated.jpg', 'keep me');

        StupImage::delete($image->path);

        expect(Storage::disk('local')->allFiles())->toBe(['avatars/unrelated.jpg'])
            ->and(Storage::disk('local')->allDirectories('avatars'))->toBeEmpty();
    });

    it('never deletes unknown files in the variants folder', function (): void {
        $image = StupImage::from(UploadedFile::fake()->image('a.jpg'))->directory('')->name('gallery')->variants(['thumbnail'])->store();
        Storage::disk('local')->put('gallery/holiday.jpg', 'keep me');

        StupImage::delete($image->path);

        expect(Storage::disk('local')->allFiles())->toBe(['gallery/holiday.jpg']);
    });

    it('removes the image when a variant fails', function (): void {
        config(['stup-image.presets.broken' => ['format' => 'psd']]);

        expect(fn () => StupImage::from(UploadedFile::fake()->image('a.jpg'))->variants(['thumbnail', 'broken'])->store())
            ->toThrow(InvalidArgumentException::class);

        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    });

    it('removes the image and written variants when the disk fails', function (): void {
        Storage::set('flaky', flakyDisk(failAfter: 2));

        expect(fn () => StupImage::from(UploadedFile::fake()->image('a.jpg'))->disk('flaky')->variants(['thumbnail', 'medium'])->store())
            ->toThrow(StorageException::class);

        expect(Storage::disk('flaky')->allFiles())->toBeEmpty();
    });

    it('rejects unknown variants', function (): void {
        StupImage::from(UploadedFile::fake()->image('a.jpg'))->variants(['huge']);
    })->throws(InvalidArgumentException::class);
});

describe('queued variants', function (): void {
    it('dispatches a job instead of generating variants', function (): void {
        Bus::fake();

        $image = StupImage::from(UploadedFile::fake()->image('a.jpg'))
            ->variants(['thumbnail'])
            ->queue('images', 'redis')
            ->store();

        expect($image->variants)->toBe([]);
        Storage::disk('local')->assertMissing(StupImage::variantPath($image->path, 'thumbnail'));

        Bus::assertDispatched(GenerateImageVariants::class, fn (GenerateImageVariants $job): bool => $job->path === $image->path
            && $job->disk === 'local'
            && $job->variants === ['thumbnail']
            && $job->queue === 'images'
            && $job->connection === 'redis');
    });

    it('does not dispatch a job without variants', function (): void {
        Bus::fake();

        StupImage::from(UploadedFile::fake()->image('a.jpg'))->queue()->store();

        Bus::assertNothingDispatched();
    });

    it('generates the variants when the job runs', function (): void {
        Event::fake([ImageStored::class]);

        $image = StupImage::from(UploadedFile::fake()->image('a.jpg', 1000, 1000))->variants(['thumbnail'])->queue()->store();

        $path = StupImage::variantPath($image->path, 'thumbnail');
        Storage::disk('local')->assertExists($path);
        expect(getimagesizefromstring((string) Storage::disk('local')->get($path)))->toMatchArray([0 => 150, 1 => 150]);
        Event::assertDispatched(ImageStored::class, fn (ImageStored $event): bool => $event->image->path === $path);
    });

    it('skips images that were deleted before the job ran', function (): void {
        (new GenerateImageVariants('local', 'gone.jpg', ['thumbnail'], 80))->handle(app(Daycode\StupImage\StupImageManager::class));

        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    });
});

/**
 * A disk that accepts the first $failAfter image writes and then refuses writes.
 */
function flakyDisk(int $failAfter): Illuminate\Contracts\Filesystem\Filesystem
{
    $root = sys_get_temp_dir().'/stup-image-flaky-'.bin2hex(random_bytes(4));

    return new class(new League\Flysystem\Filesystem($adapter = new League\Flysystem\Local\LocalFilesystemAdapter($root)), $adapter, ['root' => $root], $failAfter) extends Illuminate\Filesystem\FilesystemAdapter
    {
        public function __construct($driver, $adapter, array $config, private int $remaining)
        {
            parent::__construct($driver, $adapter, $config);
        }

        public function put($path, $contents, $options = [])
        {
            return $this->remaining-- > 0 ? parent::put($path, $contents, $options) : false;
        }
    };
}
