<?php

declare(strict_types=1);

use Daycode\StupImage\StoredImage;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('avatars/a.jpg', 'x');

    $this->image = new StoredImage(
        disk: 'public',
        path: 'avatars/a.jpg',
        filename: 'a.jpg',
        mimeType: 'image/jpeg',
        size: 1,
        width: 10,
        height: 20,
    );
});

it('casts to its path', function (): void {
    expect((string) $this->image)->toBe('avatars/a.jpg');
});

it('generates a url', function (): void {
    expect($this->image->url())->toEndWith('/avatars/a.jpg');
});

it('generates a temporary url', function (): void {
    Storage::disk('public')->buildTemporaryUrlsUsing(
        fn (string $path, DateTimeInterface $expiration): string => "https://cdn.test/{$path}?expires={$expiration->getTimestamp()}",
    );

    $expiration = now()->addMinutes(5);

    expect($this->image->temporaryUrl($expiration))->toBe("https://cdn.test/avatars/a.jpg?expires={$expiration->getTimestamp()}");
});

it('checks existence and deletes itself', function (): void {
    expect($this->image->exists())->toBeTrue()
        ->and($this->image->delete())->toBeTrue()
        ->and($this->image->exists())->toBeFalse()
        ->and($this->image->delete())->toBeFalse();
});

it('converts to an array and json', function (): void {
    $expected = [
        'disk' => 'public',
        'path' => 'avatars/a.jpg',
        'filename' => 'a.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1,
        'width' => 10,
        'height' => 20,
        'variants' => [],
    ];

    expect($this->image->toArray())->toBe($expected)
        ->and(json_decode((string) json_encode($this->image), true))->toBe($expected);
});

it('exposes its variants', function (): void {
    $thumb = new StoredImage('public', 'avatars/a/thumbnail.webp', 'thumbnail.webp', 'image/webp', 1, 5, 5);
    $image = new StoredImage('public', 'avatars/a.jpg', 'a.jpg', 'image/jpeg', 1, 10, 20, ['thumbnail' => $thumb]);

    expect($image->variant('thumbnail'))->toBe($thumb)
        ->and($image->variant('missing'))->toBeNull()
        ->and($image->toArray()['variants']['thumbnail']['path'])->toBe('avatars/a/thumbnail.webp');
});
