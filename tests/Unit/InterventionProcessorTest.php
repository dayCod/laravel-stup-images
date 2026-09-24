<?php

declare(strict_types=1);

use Daycode\StupImage\Exceptions\DriverNotAvailableException;
use Daycode\StupImage\Exceptions\InvalidImageException;
use Daycode\StupImage\Processors\InterventionProcessor;
use Illuminate\Http\UploadedFile;

function imageContents(string $name = 'photo.jpg', int $width = 400, int $height = 200): string
{
    return (string) UploadedFile::fake()->image($name, $width, $height)->get();
}

it('applies manipulations', function (string $operation, ?int $width, ?int $height, array $expected): void {
    $image = (new InterventionProcessor)->process(imageContents(), [[$operation, $width, $height]]);

    expect([$image->width, $image->height])->toBe($expected);
})->with([
    'resize ignores the aspect ratio' => ['resize', 100, 100, [100, 100]],
    'scale keeps the aspect ratio' => ['scale', 200, null, [200, 100]],
    'scale by height' => ['scale', null, 50, [100, 50]],
    'cover crops to the exact size' => ['cover', 100, 100, [100, 100]],
    'contain pads to the exact size' => ['contain', 100, 100, [100, 100]],
]);

it('applies manipulations in order', function (): void {
    $image = (new InterventionProcessor)->process(imageContents(), [['cover', 300, 300], ['scale', 100, null]]);

    expect([$image->width, $image->height])->toBe([100, 100]);
});

it('keeps the source format by default', function (string $name, string $mime, string $extension): void {
    $image = (new InterventionProcessor)->process(imageContents($name));

    expect($image->mimeType)->toBe($mime)
        ->and($image->extension)->toBe($extension)
        ->and(getimagesizefromstring($image->contents)['mime'])->toBe($mime);
})->with([
    ['photo.jpg', 'image/jpeg', 'jpg'],
    ['photo.png', 'image/png', 'png'],
    ['photo.gif', 'image/gif', 'gif'],
    ['photo.webp', 'image/webp', 'webp'],
]);

it('encodes to the requested format', function (string $format, string $mime, string $extension): void {
    $image = (new InterventionProcessor)->process(imageContents(), [], $format);

    expect($image->mimeType)->toBe($mime)
        ->and($image->extension)->toBe($extension)
        ->and(getimagesizefromstring($image->contents)['mime'])->toBe($mime);
})->with([
    ['webp', 'image/webp', 'webp'],
    ['JPEG', 'image/jpeg', 'jpg'],
    ['png', 'image/png', 'png'],
]);

it('uses the quality for lossy formats', function (): void {
    $processor = new InterventionProcessor;

    // A noisy image makes the quality difference measurable.
    $noisy = imagecreatetruecolor(300, 300);
    for ($i = 0; $i < 3000; $i++) {
        imagesetpixel($noisy, random_int(0, 299), random_int(0, 299), random_int(0, 0xFFFFFF));
    }
    ob_start();
    imagepng($noisy);
    $source = (string) ob_get_clean();

    expect($processor->process($source, [], 'jpg', 10)->size())
        ->toBeLessThan($processor->process($source, [], 'jpg', 100)->size());
});

it('rejects data that is not an image', function (): void {
    (new InterventionProcessor)->process('not an image');
})->throws(InvalidImageException::class);

it('never reads the contents as a file path', function (): void {
    (new InterventionProcessor)->process(__DIR__.'/../fixtures/does-not-matter.jpg');
})->throws(InvalidImageException::class);

it('rejects unknown operations and formats', function (array $manipulations, ?string $format): void {
    (new InterventionProcessor)->process(imageContents(), $manipulations, $format);
})->with([
    'operation' => [[['rotate', 90, null]], null],
    'format' => [[], 'psd'],
])->throws(InvalidArgumentException::class);

it('rejects unknown drivers', function (): void {
    (new InterventionProcessor('vips'))->process(imageContents());
})->throws(DriverNotAvailableException::class, 'Unsupported image driver [vips]');

it('explains a missing imagick extension', function (): void {
    (new InterventionProcessor('imagick'))->process(imageContents());
})->throws(DriverNotAvailableException::class, 'stup-image:install')
    ->skip(fn (): bool => extension_loaded('imagick'), 'imagick is installed');

it('processes images with imagick', function (): void {
    $image = (new InterventionProcessor('imagick'))->process(imageContents(), [['cover', 50, 50]], 'png');

    expect([$image->width, $image->height, $image->mimeType])->toBe([50, 50, 'image/png']);
})->skip(fn (): bool => ! extension_loaded('imagick'), 'imagick is not installed');
