# Testing

## Faking

```php
use Daycode\StupImage\Facades\StupImage;

$fake = StupImage::fake();
```

`StupImage::fake()` swaps the manager for a `StupImageFake`, which:

- calls `Storage::fake()` for every disk the package uses, the first time it's used,
- still processes images for real, so widths, heights and MIME types are accurate,
- records every stored and deleted image, and
- still dispatches `ImageStored` and `ImageDeleted`.

It also covers `StoredImage::delete()`, the `HasStupImages` trait, the `storeImage()` macro and queued variant jobs.

## Assertions

```php
$fake->assertStored();                                          // at least one image
$fake->assertStored('avatars/01j9....webp');                    // by path (it must still exist)
$fake->assertStored(fn (StoredImage $image) => $image->width === 400);
$fake->assertStored(fn (StoredImage $image) => $image->disk === 's3', times: 2);
$fake->assertStoredCount(3);
$fake->assertNothingStored();

$fake->assertDeleted('avatars/old.jpg');
$fake->assertNotDeleted('avatars/keep.jpg');
$fake->assertNothingDeleted();

$fake->storedImages();   // Collection<StoredImage>
$fake->deletedImages();  // Collection<array{disk: string, path: string}>
```

## Creating test images

Laravel's file fakes produce real images, using GD:

```php
UploadedFile::fake()->image('avatar.jpg', 800, 600);
UploadedFile::fake()->image('avatar.png')->size(2048); // report the size as 2 MB
UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'); // not an image
```

## Testing without the fake

The package writes through Laravel's filesystem, so `Storage::fake()` on its own works too:

```php
Storage::fake('public');

$image = StupImage::disk('public')->from(UploadedFile::fake()->image('a.jpg'))->store();

Storage::disk('public')->assertExists($image->path);
```

Use `Event::fake([ImageStored::class])` to assert events and `Bus::fake()` to assert queued variant jobs (`GenerateImageVariants`).
