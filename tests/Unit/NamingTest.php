<?php

declare(strict_types=1);

use Daycode\StupImage\Naming\HashNamer;
use Daycode\StupImage\Naming\OriginalNamer;
use Daycode\StupImage\Naming\UlidNamer;
use Daycode\StupImage\Naming\UuidNamer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->disk = Storage::fake('local');
    $this->file = UploadedFile::fake()->image('My Holiday Photo.JPG');
});

it('generates unique lowercase ulid names', function (): void {
    $namer = new UlidNamer;

    $names = collect(range(1, 50))->map(fn () => $namer->name($this->file, 'jpg', 'avatars', $this->disk));

    expect($names->unique())->toHaveCount(50)
        ->and($names->first())->toMatch('/^[0-9a-z]{26}\.jpg$/');
});

it('generates uuid names', function (): void {
    expect((new UuidNamer)->name($this->file, 'webp', '', $this->disk))
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}\.webp$/');
});

it('generates random hash names', function (): void {
    $namer = new HashNamer;

    $first = $namer->name($this->file, 'png', '', $this->disk);

    expect($first)->toMatch('/^[0-9a-f]{32}\.png$/')
        ->and($namer->name($this->file, 'png', '', $this->disk))->not->toBe($first);
});

it('slugs the original name without doubling the extension', function (): void {
    expect((new OriginalNamer)->name($this->file, 'jpg', 'avatars', $this->disk))->toBe('my-holiday-photo.jpg');
});

it('uses the output extension for original names', function (): void {
    expect((new OriginalNamer)->name($this->file, 'webp', 'avatars', $this->disk))->toBe('my-holiday-photo.webp');
});

it('dedupes original names', function (): void {
    $this->disk->put('avatars/my-holiday-photo.jpg', 'x');
    $this->disk->put('avatars/my-holiday-photo-1.jpg', 'x');

    expect((new OriginalNamer)->name($this->file, 'jpg', 'avatars', $this->disk))->toBe('my-holiday-photo-2.jpg');
});

it('keeps original base names unique across extensions', function (): void {
    $this->disk->put('my-holiday-photo.png', 'x');

    expect((new OriginalNamer)->name($this->file, 'jpg', '', $this->disk))->toBe('my-holiday-photo-1.jpg');
});

it('falls back to "image" when the original name has no usable characters', function (): void {
    expect((new OriginalNamer)->name(UploadedFile::fake()->image('###.jpg'), 'jpg', '', $this->disk))->toBe('image.jpg');
});
