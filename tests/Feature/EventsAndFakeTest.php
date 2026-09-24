<?php

declare(strict_types=1);

use Daycode\StupImage\Events\ImageDeleted;
use Daycode\StupImage\Events\ImageStored;
use Daycode\StupImage\Facades\StupImage;
use Daycode\StupImage\StupImageManager;
use Daycode\StupImage\Testing\StupImageFake;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\AssertionFailedError;

describe('events', function (): void {
    beforeEach(function (): void {
        Storage::fake('local');
        Event::fake();
    });

    it('dispatches ImageStored', function (): void {
        $image = StupImage::from(UploadedFile::fake()->image('a.jpg'))->store();

        Event::assertDispatched(ImageStored::class, fn (ImageStored $event): bool => $event->image === $image);
    });

    it('dispatches ImageDeleted', function (): void {
        $image = StupImage::from(UploadedFile::fake()->image('a.jpg'))->store();

        StupImage::delete($image->path);
        StupImage::delete('missing.jpg');

        Event::assertDispatchedTimes(ImageDeleted::class, 1);
        Event::assertDispatched(ImageDeleted::class, fn (ImageDeleted $event): bool => $event->disk === 'local' && $event->path === $image->path);
    });

    it('only dispatches ImageStored for a batch that succeeds', function (): void {
        try {
            StupImage::many([UploadedFile::fake()->image('a.jpg'), 'nope'])->store();
        } catch (Throwable) {
            //
        }

        Event::assertNotDispatched(ImageStored::class);
        Event::assertNotDispatched(ImageDeleted::class);

        StupImage::many([UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')])->store();

        Event::assertDispatchedTimes(ImageStored::class, 2);
    });

    it('dispatches ImageDeleted for the replaced image', function (): void {
        $old = StupImage::from(UploadedFile::fake()->image('a.jpg'))->store();

        StupImage::replace($old->path, UploadedFile::fake()->image('b.jpg'))->store();

        Event::assertDispatched(ImageDeleted::class, fn (ImageDeleted $event): bool => $event->path === $old->path);
    });
});

describe('fake', function (): void {
    it('swaps the manager', function (): void {
        $fake = StupImage::fake();

        expect($fake)->toBeInstanceOf(StupImageFake::class)
            ->and(app(StupImageManager::class))->toBe($fake);
    });

    it('stores on faked disks and records images', function (): void {
        $fake = StupImage::fake();

        $image = StupImage::from(UploadedFile::fake()->image('a.jpg'))->directory('avatars')->store();

        $fake->assertStored($image->path)
            ->assertStored()
            ->assertStored(fn ($stored) => str_starts_with($stored->path, 'avatars/'))
            ->assertStored(fn ($stored) => $stored->width === 10, times: 1)
            ->assertStoredCount(1)
            ->assertNothingDeleted();

        expect($fake->storedImages()->first())->toBe($image);
        Storage::disk('local')->assertExists($image->path);
    });

    it('fakes every disk that is used', function (): void {
        $fake = StupImage::fake();

        $image = StupImage::disk('s3')->from(UploadedFile::fake()->image('a.jpg'))->store();

        $fake->assertStored($image->path);
        Storage::disk('s3')->assertExists($image->path);
    });

    it('records deletions, including through clones and StoredImage', function (): void {
        $fake = StupImage::fake();
        $first = StupImage::from(UploadedFile::fake()->image('a.jpg'))->store();
        $second = StupImage::disk('s3')->from(UploadedFile::fake()->image('b.jpg'))->store();

        StupImage::delete($first->path);
        $second->delete();

        $fake->assertDeleted($first->path)
            ->assertDeleted($second->path)
            ->assertNotDeleted('other.jpg')
            ->assertStoredCount(2);

        expect($fake->deletedImages()->all())->toBe([
            ['disk' => 'local', 'path' => $first->path],
            ['disk' => 's3', 'path' => $second->path],
        ]);
    });

    it('asserts nothing was stored', function (): void {
        StupImage::fake()->assertNothingStored()->assertNothingDeleted();
    });

    it('fails assertions with helpful messages', function (Closure $assertion, string $message): void {
        $fake = StupImage::fake();
        $image = StupImage::from(UploadedFile::fake()->image('a.jpg'))->store();
        StupImage::delete($image->path);

        expect(fn () => $assertion($fake, $image))->toThrow(AssertionFailedError::class, $message);
    })->with([
        'missing path' => [fn ($fake) => $fake->assertStored('nope.jpg'), 'The expected image [nope.jpg] was not stored.'],
        'callback' => [fn ($fake) => $fake->assertStored(fn () => false), 'The expected image was not stored.'],
        'times' => [fn ($fake) => $fake->assertStored(times: 2), 'stored 1 times instead of 2 times'],
        'deleted since' => [fn ($fake, $image) => $fake->assertStored($image->path), 'no longer exists'],
        'count' => [fn ($fake) => $fake->assertStoredCount(3), 'Expected 3 stored images, got 1.'],
        'nothing stored' => [fn ($fake) => $fake->assertNothingStored(), 'Images were stored unexpectedly'],
        'not deleted' => [fn ($fake) => $fake->assertDeleted('nope.jpg'), 'The expected image [nope.jpg] was not deleted.'],
        'deleted' => [fn ($fake, $image) => $fake->assertNotDeleted($image->path), 'was deleted unexpectedly'],
        'nothing deleted' => [fn ($fake) => $fake->assertNothingDeleted(), 'Images were deleted unexpectedly.'],
    ]);

    it('still dispatches events', function (): void {
        Event::fake();
        StupImage::fake();

        StupImage::from(UploadedFile::fake()->image('a.jpg'))->store();

        Event::assertDispatched(ImageStored::class);
    });
});
