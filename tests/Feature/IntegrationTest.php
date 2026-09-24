<?php

declare(strict_types=1);

use Daycode\StupImage\Contracts\ImageProcessor;
use Daycode\StupImage\Facades\StupImage;
use Daycode\StupImage\Processors\InterventionProcessor;
use Daycode\StupImage\StoredImage;
use Daycode\StupImage\StupImageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

it('registers the manager and processor', function (): void {
    expect(app(StupImageManager::class))->toBe(app('stup-image'))
        ->and(app(ImageProcessor::class))->toBeInstanceOf(InterventionProcessor::class);
});

it('merges the default config', function (): void {
    expect(config('stup-image.naming'))->toBe('ulid')
        ->and(config('stup-image.allowed_mime_types'))->toContain('image/jpeg')
        ->and(config('stup-image.presets.thumbnail.cover'))->toBe([150, 150]);
});

it('uses the configured driver', function (): void {
    config(['stup-image.driver' => 'vips']);

    StupImage::from(UploadedFile::fake()->image('a.jpg'))->store();
})->throws(Daycode\StupImage\Exceptions\DriverNotAvailableException::class);

it('publishes the config with the new and the v1 tag', function (string $tag): void {
    $target = config_path('stup-image.php');
    File::delete($target);

    $this->artisan('vendor:publish', ['--tag' => $tag])->assertSuccessful();

    expect($target)->toBeFile();
    File::delete($target);
})->with(['stup-image-config', 'stup-image']);

it('adds a storeImage macro to uploaded files', function (): void {
    Storage::fake('local');
    Storage::fake('s3');

    $default = UploadedFile::fake()->image('a.jpg')->storeImage();
    $custom = UploadedFile::fake()->image('a.jpg')->storeImage('avatars', 's3');

    expect($default)->toBeInstanceOf(StoredImage::class)
        ->and($default->path)->toStartWith('images/')
        ->and($custom->path)->toStartWith('avatars/')
        ->and($custom->disk)->toBe('s3');
    Storage::disk('s3')->assertExists($custom->path);
});

describe('install command', function (): void {
    afterEach(fn () => File::delete(config_path('stup-image.php')));

    it('publishes the config and checks the driver', function (): void {
        $this->artisan('stup-image:install')
            ->expectsOutputToContain('Config file published')
            ->expectsOutputToContain('ready to use with the [gd] driver')
            ->assertSuccessful();

        expect(config_path('stup-image.php'))->toBeFile();
    });

    it('fails for an unavailable driver', function (): void {
        config(['stup-image.driver' => 'imagick']);

        $this->artisan('stup-image:install')
            ->expectsOutputToContain('The configured driver [imagick] is not available')
            ->assertFailed();
    })->skip(fn (): bool => extension_loaded('imagick'), 'imagick is installed');

    it('fails for an unsupported driver', function (): void {
        config(['stup-image.driver' => 'vips']);

        $this->artisan('stup-image:install')
            ->expectsOutputToContain('Unsupported driver [vips]')
            ->assertFailed();
    });
});
