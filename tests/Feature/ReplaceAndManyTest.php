<?php

declare(strict_types=1);

use Daycode\StupImage\Exceptions\DisallowedMimeTypeException;
use Daycode\StupImage\Exceptions\InvalidImageException;
use Daycode\StupImage\Exceptions\StorageException;
use Daycode\StupImage\Facades\StupImage;
use Daycode\StupImage\StoredImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('local');
});

describe('replace', function (): void {
    it('deletes the old image after storing the new one', function (): void {
        $old = StupImage::from(UploadedFile::fake()->image('old.jpg'))->directory('avatars')->store();

        $new = StupImage::replace($old->path, UploadedFile::fake()->image('new.jpg'))->directory('avatars')->store();

        Storage::disk('local')->assertMissing($old->path);
        Storage::disk('local')->assertExists($new->path);
    });

    it('keeps the old image when the new one fails', function (): void {
        $old = StupImage::from(UploadedFile::fake()->image('old.jpg'))->directory('avatars')->store();

        expect(fn () => StupImage::replace($old->path, UploadedFile::fake()->create('doc.pdf', 1, 'application/pdf'))->store())
            ->toThrow(DisallowedMimeTypeException::class);

        Storage::disk('local')->assertExists($old->path);
    });

    it('keeps the old image when the disk fails', function (): void {
        Storage::set('broken', brokenDisk(put: false));
        Storage::disk('broken')->put('avatars/old.jpg', 'old');

        expect(fn () => StupImage::replace('avatars/old.jpg', UploadedFile::fake()->image('new.jpg'))->disk('broken')->store())
            ->toThrow(StorageException::class);

        expect(Storage::disk('broken')->exists('avatars/old.jpg'))->toBeTrue();
    });

    it('works without an old image', function (?string $old): void {
        $new = StupImage::replace($old, UploadedFile::fake()->image('new.jpg'))->store();

        Storage::disk('local')->assertExists($new->path);
    })->with([null, '']);

    it('ignores an old image that no longer exists', function (): void {
        $new = StupImage::replace('avatars/gone.jpg', UploadedFile::fake()->image('new.jpg'))->store();

        Storage::disk('local')->assertExists($new->path);
    });

    it('does not delete the new image when it has the same path as the old one', function (): void {
        $old = StupImage::from(UploadedFile::fake()->image('a.jpg'))->name('logo')->store();

        $new = StupImage::replace($old->path, UploadedFile::fake()->image('b.jpg'))->name('logo')->store();

        expect($new->path)->toBe($old->path);
        Storage::disk('local')->assertExists($new->path);
    });

    it('returns the new image even when the old one cannot be deleted', function (): void {
        Log::spy();
        Storage::set('undeletable', brokenDisk(delete: false));
        Storage::disk('undeletable')->put('old.jpg', 'old');

        $new = StupImage::replace('old.jpg', UploadedFile::fake()->image('new.jpg'))->disk('undeletable')->store();

        expect(Storage::disk('undeletable')->exists($new->path))->toBeTrue()
            ->and(Storage::disk('undeletable')->exists('old.jpg'))->toBeTrue();
        Log::shouldHaveReceived('error')->once();
    });
});

describe('many', function (): void {
    it('stores every file', function (): void {
        $images = StupImage::many([
            UploadedFile::fake()->image('a.jpg', 1600, 1200),
            UploadedFile::fake()->image('b.png', 2000, 1000),
        ])->directory('gallery')->scale(width: 1200)->store();

        expect($images)->toBeInstanceOf(Collection::class)->toHaveCount(2)
            ->and($images->map(fn (StoredImage $image) => [$image->width, $image->height])->all())->toBe([[1200, 900], [1200, 600]]);

        Storage::disk('local')->assertExists($images->map->path->all());
    });

    it('accepts any iterable and null', function (): void {
        $files = (function () {
            yield UploadedFile::fake()->image('a.jpg');
        })();

        expect(StupImage::many($files)->store())->toHaveCount(1)
            ->and(StupImage::many(null)->store())->toBeEmpty()
            ->and(StupImage::many([])->store())->toBeEmpty();
    });

    it('cleans up stored files when one fails', function (): void {
        $files = [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
            UploadedFile::fake()->create('c.pdf', 1, 'application/pdf'),
        ];

        expect(fn () => StupImage::many($files)->directory('gallery')->store())->toThrow(DisallowedMimeTypeException::class);

        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    });

    it('cleans up stored variants when one fails', function (): void {
        $files = [UploadedFile::fake()->image('a.jpg'), 'nope'];

        expect(fn () => StupImage::many($files)->variants(['thumbnail'])->store())->toThrow(InvalidImageException::class);

        expect(Storage::disk('local')->allFiles())->toBeEmpty();
    });

    it('reports cleanup errors and rethrows the original error', function (): void {
        Log::spy();
        Storage::set('undeletable', brokenDisk(delete: new RuntimeException('nope')));

        expect(fn () => StupImage::many([UploadedFile::fake()->image('a.jpg'), 'nope'])->disk('undeletable')->store())
            ->toThrow(InvalidImageException::class);

        Log::shouldHaveReceived('error')->once();
    });

    it('rejects values that are not uploaded files', function (): void {
        StupImage::many([UploadedFile::fake()->image('a.jpg'), 'b.jpg'])->store();
    })->throws(InvalidImageException::class);
});

describe('delete', function (): void {
    it('deletes an image', function (): void {
        $image = StupImage::from(UploadedFile::fake()->image('a.jpg'))->store();

        expect(StupImage::delete($image->path))->toBeTrue();
        Storage::disk('local')->assertMissing($image->path);
    });

    it('does not fail on missing files or empty paths', function (?string $path): void {
        expect(StupImage::delete($path))->toBeFalse();
    })->with(['missing.jpg', null, '']);

    it('deletes when no presets are configured', function (): void {
        config(['stup-image.presets' => []]);
        $image = StupImage::from(UploadedFile::fake()->image('a.jpg'))->store();

        expect(StupImage::delete($image->path))->toBeTrue();
    });

    it('deletes from a specific disk', function (): void {
        Storage::fake('s3');
        $image = StupImage::from(UploadedFile::fake()->image('a.jpg'))->disk('s3')->store();

        expect(StupImage::delete($image->path))->toBeFalse()
            ->and(StupImage::disk('s3')->delete($image->path))->toBeTrue();
        Storage::disk('s3')->assertMissing($image->path);
    });

    it('throws a storage exception when the disk cannot delete', function (): void {
        Storage::set('undeletable', brokenDisk(delete: false));
        Storage::disk('undeletable')->put('a.jpg', 'x');

        StupImage::disk('undeletable')->delete('a.jpg');
    })->throws(StorageException::class, 'Unable to delete');

    it('wraps delete errors in a storage exception', function (): void {
        Storage::set('undeletable', brokenDisk(delete: new RuntimeException('nope')));
        Storage::disk('undeletable')->put('a.jpg', 'x');

        StupImage::disk('undeletable')->delete('a.jpg');
    })->throws(StorageException::class, 'Unable to delete');
});

it('checks existence and builds urls', function (): void {
    Storage::fake('public');
    $image = StupImage::disk('public')->from(UploadedFile::fake()->image('a.jpg'))->store();

    expect(StupImage::disk('public')->exists($image->path))->toBeTrue()
        ->and(StupImage::disk('public')->exists(null))->toBeFalse()
        ->and(StupImage::disk('public')->url($image->path))->toBe($image->url())
        ->and(StupImage::url(null))->toBeNull()
        ->and(StupImage::url(''))->toBeNull();
});
