<?php

declare(strict_types=1);

use Daycode\StupImage\Stupable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
 * Characterization tests for the v1 `Stupable` trait API.
 *
 * Tests without a bug ID document the behaviour that must survive the v2
 * rewrite. Tests tagged with a bug ID (B1–B8, see docs/IMPLEMENTATION_PLAN.md)
 * describe the *correct* behaviour that v1 did not have; they were skipped
 * against v1 and pass since v2.
 */

function stupable(): object
{
    return new class
    {
        use Stupable;
    };
}

beforeEach(function (): void {
    Storage::fake('local');
});

it('uploads a file and returns its hashed filename', function (): void {
    $filename = stupable()->uploadFile(UploadedFile::fake()->image('photo.jpg', 200, 200), 'avatars');

    expect($filename)->toBeString()->toMatch('/^[a-zA-Z0-9]+\.jpg$/')
        ->and($filename)->not->toContain('photo');

    Storage::disk('local')->assertExists("avatars/{$filename}");
});

it('resizes the image to the exact given dimensions', function (): void {
    $filename = stupable()->uploadFile(UploadedFile::fake()->image('photo.jpg', 200, 200), 'avatars', [100, 50]);

    [$width, $height] = getimagesize(Storage::disk('local')->path("avatars/{$filename}"));

    expect([$width, $height])->toBe([100, 50]);
});

it('replaces the old file when syncing an upload', function (): void {
    $stupable = stupable();
    $old = $stupable->uploadFile(UploadedFile::fake()->image('old.jpg'), 'avatars');

    $new = $stupable->syncUploadFile(UploadedFile::fake()->image('new.jpg'), $old, 'avatars');

    expect($new)->not->toBe($old);
    Storage::disk('local')->assertMissing("avatars/{$old}");
    Storage::disk('local')->assertExists("avatars/{$new}");
});

it('uploads when syncing without an old file', function (): void {
    $new = stupable()->syncUploadFile(UploadedFile::fake()->image('new.jpg'), null, 'avatars');

    Storage::disk('local')->assertExists("avatars/{$new}");
});

it('uploads multiple files and returns their filenames', function (): void {
    $filenames = stupable()->uploadMultipleFiles([
        UploadedFile::fake()->image('a.jpg'),
        UploadedFile::fake()->image('b.png'),
    ], 'gallery');

    expect($filenames)->toHaveCount(2);

    foreach ($filenames as $filename) {
        Storage::disk('local')->assertExists("gallery/{$filename}");
    }
});

it('deletes a file', function (): void {
    $stupable = stupable();
    $filename = $stupable->uploadFile(UploadedFile::fake()->image('photo.jpg'), 'avatars');

    $stupable->deleteFile($filename, 'avatars');

    Storage::disk('local')->assertMissing("avatars/{$filename}");
});

it('does not fail when deleting a missing file', function (): void {
    stupable()->deleteFile('missing.jpg', 'avatars');

    expect(true)->toBeTrue();
});

it('[B1] stores on the configured default disk, including cloud disks', function (): void {
    Storage::fake('s3');
    config(['filesystems.default' => 's3']);

    $filename = stupable()->uploadFile(UploadedFile::fake()->image('photo.jpg'), 'avatars');

    Storage::disk('s3')->assertExists("avatars/{$filename}");
});

it('[B2] throws instead of returning an exception for a disallowed file', function (): void {
    config(['stup-image.allowed_mime_types' => ['image/png']]);

    stupable()->uploadFile(UploadedFile::fake()->image('photo.jpg'), 'avatars');
})->throws(Daycode\StupImage\Exceptions\DisallowedMimeTypeException::class);

it('[B3] keeps the old file when the replacement upload fails', function (): void {
    $stupable = stupable();
    $old = $stupable->uploadFile(UploadedFile::fake()->image('old.jpg'), 'avatars');

    try {
        $stupable->syncUploadFile(UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'), $old, 'avatars');
    } catch (Throwable) {
        //
    }

    Storage::disk('local')->assertExists("avatars/{$old}");
});

it('[B6] does not double the extension when filenames are not hashed', function (): void {
    config(['stup-image.naming' => 'original']);

    $filename = stupable()->uploadFile(UploadedFile::fake()->image('My Photo.jpg'), 'avatars');

    expect($filename)->toBe('my-photo.jpg');
});

it('[B7] accepts uppercase extensions', function (): void {
    $filename = stupable()->uploadFile(UploadedFile::fake()->image('PHOTO.JPG'), 'avatars');

    Storage::disk('local')->assertExists("avatars/{$filename}");
});

it('[B8] generates unique filenames for identical uploads in the same second', function (): void {
    $stupable = stupable();

    $first = $stupable->uploadFile(UploadedFile::fake()->image('photo.jpg'), 'avatars');
    $second = $stupable->uploadFile(UploadedFile::fake()->image('photo.jpg'), 'avatars');

    expect($first)->not->toBe($second);
});
