<?php

declare(strict_types=1);

use Daycode\StupImage\Facades\StupImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

it('reports invalid config values clearly', function (string $key, mixed $value, string $message): void {
    config(["stup-image.{$key}" => $value]);

    expect(fn () => StupImage::from(UploadedFile::fake()->image('a.jpg'))->store())
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    ['max_size', 'big', '[stup-image.max_size] must be an integer or null'],
    ['quality', 1.5, '[stup-image.quality] must be an integer or null'],
    ['directory', ['a'], '[stup-image.directory] must be a string or null'],
    ['allowed_mime_types', 'image/jpeg', '[stup-image.allowed_mime_types] must be an array of strings or null'],
    ['allowed_mime_types', ['image/jpeg', 1], '[stup-image.allowed_mime_types] must be an array of strings or null'],
]);

it('accepts numeric strings for integer config values (e.g. from env)', function (): void {
    config(['stup-image.max_width' => '50']);

    expect(fn () => StupImage::from(UploadedFile::fake()->image('a.jpg', 60, 10))->store())
        ->toThrow(Daycode\StupImage\Exceptions\ImageTooLargeException::class);
});

it('treats an empty disk as the default disk', function (): void {
    config(['stup-image.disk' => '']);

    expect(StupImage::from(UploadedFile::fake()->image('a.jpg'))->store()->disk)->toBe('local');
});

it('reports invalid presets clearly', function (array $preset, string $message): void {
    config(['stup-image.presets.bad' => $preset]);

    expect(fn () => StupImage::from(UploadedFile::fake()->image('a.jpg'))->preset('bad'))
        ->toThrow(InvalidArgumentException::class, $message);
})->with([
    [['cover' => ['150', 150]], '[stup-image.presets.bad.cover] must be a width and height'],
    [['format' => 5], '[stup-image.presets.bad.format] must be a string or null'],
    [['quality' => 'high'], '[stup-image.presets.bad.quality] must be an integer or null'],
    [['format' => 'psd'], 'Unsupported image format [psd]'],
]);

it('lists the preset names', function (): void {
    expect(StupImage::presetNames())->toBe(['thumbnail', 'medium']);

    config(['stup-image.presets' => null]);

    expect(StupImage::presetNames())->toBe([]);
});
